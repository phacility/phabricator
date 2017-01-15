<?php

final class DiffusionMercurialServeSSHWorkflow
  extends DiffusionMercurialSSHWorkflow {

  protected $didSeeWrite;

  protected function didConstruct() {
    $this->setName('hg');
    $this->setArguments(
      array(
        array(
          'name' => 'repository',
          'short' => 'R',
          'param' => 'repo',
        ),
        array(
          'name' => 'stdio',
        ),
        array(
          'name' => 'command',
          'wildcard' => true,
        ),
      ));
  }

  protected function identifyRepository() {
    $args = $this->getArgs();
    $path = $args->getArg('repository');
    return $this->loadRepositoryWithPath(
      $path,
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL);
  }

  protected function executeRepositoryOperations() {
    $repository = $this->getRepository();
    $args = $this->getArgs();

    if (!$args->getArg('stdio')) {
      throw new Exception(pht('Expected `%s`!', 'hg ... --stdio'));
    }

    if ($args->getArg('command') !== array('serve')) {
      throw new Exception(pht('Expected `%s`!', 'hg ... serve'));
    }

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();
    } else {
      $command = csprintf(
        'hg -R %s serve --stdio',
        $repository->getLocalPath());
    }
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    $io_channel = $this->getIOChannel();
    $protocol_channel = new DiffusionMercurialWireClientSSHProtocolChannel(
      $io_channel);

    $err = id($this->newPassthruCommand())
      ->setIOChannel($protocol_channel)
      ->setCommandChannelFromExecFuture($future)
      ->setWillWriteCallback(array($this, 'willWriteMessageCallback'))
      ->execute();

    // TODO: It's apparently technically possible to communicate errors to
    // Mercurial over SSH by writing a special "\n<error>\n-\n" string. However,
    // my attempt to implement that resulted in Mercurial closing the socket and
    // then hanging, without showing the error. This might be an issue on our
    // side (we need to close our half of the socket?), or maybe the code
    // for this in Mercurial doesn't actually work, or maybe something else
    // is afoot. At some point, we should look into doing this more cleanly.
    // For now, when we, e.g., reject writes for policy reasons, the user will
    // see "abort: unexpected response: empty string" after the diagnostically
    // useful, e.g., "remote: This repository is read-only over SSH." message.

    if (!$err && $this->didSeeWrite) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
    }

    return $err;
  }

  public function willWriteMessageCallback(
    PhabricatorSSHPassthruCommand $command,
    $message) {

    $command = $message['command'];

    // Check if this is a readonly command.

    $is_readonly = false;
    if ($command == 'batch') {
      $cmds = idx($message['arguments'], 'cmds');
      if (DiffusionMercurialWireProtocol::isReadOnlyBatchCommand($cmds)) {
        $is_readonly = true;
      }
    } else if (DiffusionMercurialWireProtocol::isReadOnlyCommand($command)) {
      $is_readonly = true;
    }

    if (!$is_readonly) {
      $this->requireWriteAccess();
      $this->didSeeWrite = true;
    }

    $raw_message = $message['raw'];
    if ($command == 'capabilities') {
      $raw_message = DiffusionMercurialWireProtocol::filterBundle2Capability(
        $raw_message);
    }

    // If we're good, return the raw message data.
    return $raw_message;
  }

  protected function raiseWrongVCSException(
    PhabricatorRepository $repository) {
    throw new Exception(
      pht(
        'This repository ("%s") is not a Mercurial repository. Use "%s" to '.
        'interact with this repository.',
        $repository->getDisplayName(),
        $repository->getVersionControlSystem()));
  }

}
