<?php

abstract class DiffusionGitSSHWorkflow
  extends DiffusionSSHWorkflow
  implements DiffusionRepositoryClusterEngineLogInterface {

  private $engineLogProperties = array();
  private $protocolLog;

  private $wireProtocol;

  protected function writeError($message) {
    // Git assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

  public function writeClusterEngineLogMessage($message) {
    parent::writeError($message);
    $this->getErrorChannel()->update();
  }

  public function writeClusterEngineLogProperty($key, $value) {
    $this->engineLogProperties[$key] = $value;
  }

  protected function getClusterEngineLogProperty($key, $default = null) {
    return idx($this->engineLogProperties, $key, $default);
  }

  protected function identifyRepository() {
    $args = $this->getArgs();
    $path = head($args->getArg('dir'));
    return $this->loadRepositoryWithPath(
      $path,
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);
  }

  protected function waitForGitClient() {
    $io_channel = $this->getIOChannel();

    // If we don't wait for the client to close the connection, `git` will
    // consider it an early abort and fail. Sit around until Git is comfortable
    // that it really received all the data.
    while ($io_channel->isOpenForReading()) {
      $io_channel->update();
      $this->getErrorChannel()->flush();
      PhutilChannel::waitForAny(array($io_channel));
    }
  }

  protected function raiseWrongVCSException(
    PhabricatorRepository $repository) {
    throw new Exception(
      pht(
        'This repository ("%s") is not a Git repository. Use "%s" to '.
        'interact with this repository.',
        $repository->getDisplayName(),
        $repository->getVersionControlSystem()));
  }

  protected function newPassthruCommand() {
    return parent::newPassthruCommand()
      ->setWillWriteCallback(array($this, 'willWriteMessageCallback'))
      ->setWillReadCallback(array($this, 'willReadMessageCallback'));
  }

  protected function newProtocolLog($is_proxy) {
    if ($is_proxy) {
      return null;
    }

    // While developing, do this to write a full protocol log to disk:
    //
    // return new PhabricatorProtocolLog('/tmp/git-protocol.log');

    return null;
  }

  final protected function getProtocolLog() {
    return $this->protocolLog;
  }

  final protected function setProtocolLog(PhabricatorProtocolLog $log) {
    $this->protocolLog = $log;
  }

  final protected function getWireProtocol() {
    return $this->wireProtocol;
  }

  final protected function setWireProtocol(
    DiffusionGitWireProtocol $protocol) {
    $this->wireProtocol = $protocol;
    return $this;
  }

  public function willWriteMessageCallback(
    PhabricatorSSHPassthruCommand $command,
    $message) {

    $log = $this->getProtocolLog();
    if ($log) {
      $log->didWriteBytes($message);
    }

    $protocol = $this->getWireProtocol();
    if ($protocol) {
      $message = $protocol->willWriteBytes($message);
    }

    return $message;
  }

  public function willReadMessageCallback(
    PhabricatorSSHPassthruCommand $command,
    $message) {

    $log = $this->getProtocolLog();
    if ($log) {
      $log->didReadBytes($message);
    }

    $protocol = $this->getWireProtocol();
    if ($protocol) {
      $message = $protocol->willReadBytes($message);
    }

    return $message;
  }

}
