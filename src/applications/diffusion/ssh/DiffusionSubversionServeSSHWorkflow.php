<?php

/**
 * This protocol has a good spec here:
 *
 *   http://svn.apache.org/repos/asf/subversion/trunk/subversion/libsvn_ra_svn/protocol
 *
 */
final class DiffusionSubversionServeSSHWorkflow
  extends DiffusionSubversionSSHWorkflow {

  private $didSeeWrite;

  private $inProtocol;
  private $outProtocol;

  private $inSeenGreeting;

  private $outPhaseCount = 0;

  private $internalBaseURI;
  private $externalBaseURI;
  private $peekBuffer;
  private $command;

  private function getCommand() {
    return $this->command;
  }

  protected function didConstruct() {
    $this->setName('svnserve');
    $this->setArguments(
      array(
        array(
          'name' => 'tunnel',
          'short' => 't',
        ),
      ));
  }

  protected function identifyRepository() {
    // NOTE: In SVN, we need to read the first few protocol frames before we
    // can determine which repository the user is trying to access. We're
    // going to peek at the data on the wire to identify the repository.

    $io_channel = $this->getIOChannel();

    // Before the client will send us the first protocol frame, we need to send
    // it a connection frame with server capabilities. To figure out the
    // correct frame we're going to start `svnserve`, read the frame from it,
    // send it to the client, then kill the subprocess.

    // TODO: This is pretty inelegant and the protocol frame will change very
    // rarely. We could cache it if we can find a reasonable way to dirty the
    // cache.

    $command = csprintf('svnserve -t');
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);
    $future = new ExecFuture('%C', $command);
    $exec_channel = new PhutilExecChannel($future);
    $exec_protocol = new DiffusionSubversionWireProtocol();

    while (true) {
      PhutilChannel::waitForAny(array($exec_channel));
      $exec_channel->update();

      $exec_message = $exec_channel->read();
      if ($exec_message !== null) {
        $messages = $exec_protocol->writeData($exec_message);
        if ($messages) {
          $message = head($messages);
          $raw = $message['raw'];

          // Write the greeting frame to the client.
          $io_channel->write($raw);

          // Kill the subprocess.
          $future->resolveKill();
          break;
        }
      }

      if (!$exec_channel->isOpenForReading()) {
        throw new Exception(
          pht(
            '%s subprocess exited before emitting a protocol frame.',
            'svnserve'));
      }
    }

    $io_protocol = new DiffusionSubversionWireProtocol();
    while (true) {
      PhutilChannel::waitForAny(array($io_channel));
      $io_channel->update();

      $in_message = $io_channel->read();
      if ($in_message !== null) {
        $this->peekBuffer .= $in_message;
        if (strlen($this->peekBuffer) > (1024 * 1024)) {
          throw new Exception(
            pht(
              'Client transmitted more than 1MB of data without transmitting '.
              'a recognizable protocol frame.'));
        }

        $messages = $io_protocol->writeData($in_message);
        if ($messages) {
          $message = head($messages);
          $struct = $message['structure'];

          // This is the:
          //
          //   ( version ( cap1 ... ) url ... )
          //
          // The `url` allows us to identify the repository.

          $uri = $struct[2]['value'];
          $path = $this->getPathFromSubversionURI($uri);

          return $this->loadRepositoryWithPath($path);
        }
      }

      if (!$io_channel->isOpenForReading()) {
        throw new Exception(
          pht(
            'Client closed connection before sending a complete protocol '.
            'frame.'));
      }

      // If the client has disconnected, kill the subprocess and bail.
      if (!$io_channel->isOpenForWriting()) {
        throw new Exception(
          pht(
            'Client closed connection before receiving response.'));
      }
    }
  }

  protected function executeRepositoryOperations() {
    $repository = $this->getRepository();

    $args = $this->getArgs();
    if (!$args->getArg('tunnel')) {
      throw new Exception(pht('Expected `%s`!', 'svnserve -t'));
    }

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();
    } else {
      $command = csprintf(
        'svnserve -t --tunnel-user=%s',
        $this->getUser()->getUsername());
    }

    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);
    $future = new ExecFuture('%C', $command);

    $this->inProtocol = new DiffusionSubversionWireProtocol();
    $this->outProtocol = new DiffusionSubversionWireProtocol();

    $this->command = id($this->newPassthruCommand())
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->setWillWriteCallback(array($this, 'willWriteMessageCallback'))
      ->setWillReadCallback(array($this, 'willReadMessageCallback'));

    $this->command->setPauseIOReads(true);

    $err = $this->command->execute();

    if (!$err && $this->didSeeWrite) {
      $this->getRepository()->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
    }

    return $err;
  }

  public function willWriteMessageCallback(
    PhabricatorSSHPassthruCommand $command,
    $message) {

    $proto = $this->inProtocol;
    $messages = $proto->writeData($message);

    $result = array();
    foreach ($messages as $message) {
      $message_raw = $message['raw'];
      $struct = $message['structure'];

      if (!$this->inSeenGreeting) {
        $this->inSeenGreeting = true;

        // The first message the client sends looks like:
        //
        //   ( version ( cap1 ... ) url ... )
        //
        // We want to grab the URL, load the repository, make sure it exists and
        // is accessible, and then replace it with the location of the
        // repository on disk.

        $uri = $struct[2]['value'];
        $struct[2]['value'] = $this->makeInternalURI($uri);

        $message_raw = $proto->serializeStruct($struct);
      } else if (isset($struct[0]) && $struct[0]['type'] == 'word') {

        if (!$proto->isReadOnlyCommand($struct)) {
          $this->didSeeWrite = true;
          $this->requireWriteAccess($struct[0]['value']);
        }

        // Several other commands also pass in URLs. We need to translate
        // all of these into the internal representation; this also makes sure
        // they're valid and accessible.

        switch ($struct[0]['value']) {
          case 'reparent':
            // ( reparent ( url ) )
            $struct[1]['value'][0]['value'] = $this->makeInternalURI(
              $struct[1]['value'][0]['value']);
            $message_raw = $proto->serializeStruct($struct);
            break;
          case 'switch':
            // ( switch ( ( rev ) target recurse url ... ) )
            $struct[1]['value'][3]['value'] = $this->makeInternalURI(
              $struct[1]['value'][3]['value']);
            $message_raw = $proto->serializeStruct($struct);
            break;
          case 'diff':
            // ( diff ( ( rev ) target recurse ignore-ancestry url ... ) )
            $struct[1]['value'][4]['value'] = $this->makeInternalURI(
              $struct[1]['value'][4]['value']);
            $message_raw = $proto->serializeStruct($struct);
            break;
          case 'add-file':
          case 'add-dir':
            // ( add-file ( path dir-token file-token [ copy-path copy-rev ] ) )
            // ( add-dir ( path parent child [ copy-path copy-rev ] ) )
            if (isset($struct[1]['value'][3]['value'][0]['value'])) {
              $copy_from = $struct[1]['value'][3]['value'][0]['value'];
              $copy_from = $this->makeInternalURI($copy_from);
              $struct[1]['value'][3]['value'][0]['value'] = $copy_from;
            }
            $message_raw = $proto->serializeStruct($struct);
            break;
        }
      }

      $result[] = $message_raw;
    }

    if (!$result) {
      return null;
    }

    return implode('', $result);
  }

  public function willReadMessageCallback(
    PhabricatorSSHPassthruCommand $command,
    $message) {

    $proto = $this->outProtocol;
    $messages = $proto->writeData($message);

    $result = array();
    foreach ($messages as $message) {
      $message_raw = $message['raw'];
      $struct = $message['structure'];

      if (isset($struct[0]) && ($struct[0]['type'] == 'word')) {

        if ($struct[0]['value'] == 'success') {
          switch ($this->outPhaseCount) {
            case 0:
              // This is the "greeting", which announces capabilities.

              // We already sent this when we were figuring out which
              // repository this request is for, so we aren't going to send
              // it again.

              // Instead, we're going to replay the client's response (which
              // we also already read).

              $command = $this->getCommand();
              $command->writeIORead($this->peekBuffer);
              $command->setPauseIOReads(false);

              $message_raw = null;
              break;
            case 1:
              // This responds to the client greeting, and announces auth.
              break;
            case 2:
              // This responds to auth, which should be trivial over SSH.
              break;
            case 3:
              // This contains the URI of the repository. We need to edit it;
              // if it does not match what the client requested it will reject
              // the response.
              $struct[1]['value'][1]['value'] = $this->makeExternalURI(
                $struct[1]['value'][1]['value']);
              $message_raw = $proto->serializeStruct($struct);
              break;
            default:
              // We don't care about other protocol frames.
              break;
          }

          $this->outPhaseCount++;
        } else if ($struct[0]['value'] == 'failure') {
          // Find any error messages which include the internal URI, and
          // replace the text with the external URI.
          foreach ($struct[1]['value'] as $key => $error) {
            $code = $error['value'][0]['value'];
            $message = $error['value'][1]['value'];

            $message = str_replace(
              $this->internalBaseURI,
              $this->externalBaseURI,
              $message);

            // Derp derp derp derp derp. The structure looks like this:
            //   ( failure ( ( code message ... ) ... ) )
            $struct[1]['value'][$key]['value'][1]['value'] = $message;
          }
          $message_raw = $proto->serializeStruct($struct);
        }

      }

      if ($message_raw !== null) {
        $result[] = $message_raw;
      }
    }

    if (!$result) {
      return null;
    }

    return implode('', $result);
  }

  private function getPathFromSubversionURI($uri_string) {
    $uri = new PhutilURI($uri_string);

    $proto = $uri->getProtocol();
    if ($proto !== 'svn+ssh') {
      throw new Exception(
        pht(
          'Protocol for URI "%s" MUST be "%s".',
          $uri_string,
          'svn+ssh'));
    }
    $path = $uri->getPath();

    // Subversion presumably deals with this, but make sure there's nothing
    // sketchy going on with the URI.
    if (preg_match('(/\\.\\./)', $path)) {
      throw new Exception(
        pht(
          'String "%s" is invalid in path specification "%s".',
          '/../',
          $uri_string));
    }

    $path = $this->normalizeSVNPath($path);

    return $path;
  }

  private function makeInternalURI($uri_string) {
    $uri = new PhutilURI($uri_string);

    $repository = $this->getRepository();

    $path = $this->getPathFromSubversionURI($uri_string);
    $path = preg_replace(
      '(^/diffusion/[A-Z]+)',
      rtrim($repository->getLocalPath(), '/'),
      $path);

    if (preg_match('(^/diffusion/[A-Z]+/\z)', $path)) {
      $path = rtrim($path, '/');
    }

    // NOTE: We are intentionally NOT removing username information from the
    // URI. Subversion retains it over the course of the request and considers
    // two repositories with different username identifiers to be distinct and
    // incompatible.

    $uri->setPath($path);

    // If this is happening during the handshake, these are the base URIs for
    // the request.
    if ($this->externalBaseURI === null) {
      $pre = (string)id(clone $uri)->setPath('');

      $external_path = '/diffusion/'.$repository->getCallsign();
      $external_path = $this->normalizeSVNPath($external_path);
      $this->externalBaseURI = $pre.$external_path;

      $internal_path = rtrim($repository->getLocalPath(), '/');
      $internal_path = $this->normalizeSVNPath($internal_path);
      $this->internalBaseURI = $pre.$internal_path;
    }

    return (string)$uri;
  }

  private function makeExternalURI($uri) {
    $internal = $this->internalBaseURI;
    $external = $this->externalBaseURI;

    if (strncmp($uri, $internal, strlen($internal)) === 0) {
      $uri = $external.substr($uri, strlen($internal));
    }

    return $uri;
  }

  private function normalizeSVNPath($path) {
    // Subversion normalizes redundant slashes internally, so normalize them
    // here as well to make sure things match up.
    $path = preg_replace('(/+)', '/', $path);

    return $path;
  }

}
