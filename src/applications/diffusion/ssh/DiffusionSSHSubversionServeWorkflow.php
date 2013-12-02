<?php

/**
 * This protocol has a good spec here:
 *
 *   http://svn.apache.org/repos/asf/subversion/trunk/subversion/libsvn_ra_svn/protocol
 *
 */
final class DiffusionSSHSubversionServeWorkflow
  extends DiffusionSSHSubversionWorkflow {

  private $didSeeWrite;

  private $inProtocol;
  private $outProtocol;

  private $inSeenGreeting;

  private $outPhaseCount = 0;

  private $internalBaseURI;
  private $externalBaseURI;

  public function didConstruct() {
    $this->setName('svnserve');
    $this->setArguments(
      array(
        array(
          'name' => 'tunnel',
          'short' => 't',
        ),
      ));
  }

  protected function executeRepositoryOperations() {
    $args = $this->getArgs();
    if (!$args->getArg('tunnel')) {
      throw new Exception("Expected `svnserve -t`!");
    }

    $command = csprintf(
      'svnserve -t --tunnel-user=%s',
      $this->getUser()->getUsername());
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $future = new ExecFuture('%C', $command);

    $this->inProtocol = new DiffusionSubversionWireProtocol();
    $this->outProtocol = new DiffusionSubversionWireProtocol();

    $err = id($this->newPassthruCommand())
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->setWillWriteCallback(array($this, 'willWriteMessageCallback'))
      ->setWillReadCallback(array($this, 'willReadMessageCallback'))
      ->execute();

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

      $result[] = $message_raw;
    }

    if (!$result) {
      return null;
    }

    return implode('', $result);
  }

  private function makeInternalURI($uri_string) {
    $uri = new PhutilURI($uri_string);

    $proto = $uri->getProtocol();
    if ($proto !== 'svn+ssh') {
      throw new Exception(
        pht(
          'Protocol for URI "%s" MUST be "svn+ssh".',
          $uri_string));
    }

    $path = $uri->getPath();

    // Subversion presumably deals with this, but make sure there's nothing
    // skethcy going on with the URI.
    if (preg_match('(/\\.\\./)', $path)) {
      throw new Exception(
        pht(
          'String "/../" is invalid in path specification "%s".',
          $uri_string));
    }

    $repository = $this->loadRepository($path);

    $path = preg_replace(
      '(^/diffusion/[A-Z]+)',
      rtrim($repository->getLocalPath(), '/'),
      $path);

    if (preg_match('(^/diffusion/[A-Z]+/$)', $path)) {
      $path = rtrim($path, '/');
    }

    $uri->setPath($path);

    // If this is happening during the handshake, these are the base URIs for
    // the request.
    if ($this->externalBaseURI === null) {
      $pre = (string)id(clone $uri)->setPath('');
      $this->externalBaseURI = $pre.'/diffusion/'.$repository->getCallsign();
      $this->internalBaseURI = $pre.rtrim($repository->getLocalPath(), '/');
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

}
