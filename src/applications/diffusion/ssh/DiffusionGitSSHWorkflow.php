<?php

abstract class DiffusionGitSSHWorkflow
  extends DiffusionSSHWorkflow
  implements DiffusionRepositoryClusterEngineLogInterface {

  private $engineLogProperties = array();
  private $protocolLog;

  private $wireProtocol;
  private $ioBytesRead = 0;
  private $ioBytesWritten = 0;
  private $requestAttempts = 0;
  private $requestFailures = 0;

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

    $this->ioBytesWritten += strlen($message);

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

    // Note that bytes aren't counted until they're emittted by the protocol
    // layer. This means the underlying command might emit bytes, but if they
    // are buffered by the protocol layer they won't count as read bytes yet.

    $this->ioBytesRead += strlen($message);

    return $message;
  }

  final protected function getIOBytesRead() {
    return $this->ioBytesRead;
  }

  final protected function getIOBytesWritten() {
    return $this->ioBytesWritten;
  }

  final protected function executeRepositoryProxyOperations($for_write) {
    $device = AlmanacKeys::getLiveDevice();

    $refs = $this->getAlmanacServiceRefs($for_write);
    $err = 1;

    while (true) {
      $ref = head($refs);

      $command = $this->getProxyCommandForServiceRef($ref);

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Request received by \"%s\", forwarding to cluster ".
            "host \"%s\".\n",
            $device->getName(),
            $ref->getDeviceName()));
      }

      $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

      $future = id(new ExecFuture('%C', $command))
        ->setEnv($this->getEnvironment());

      $this->didBeginRequest();

      $err = $this->newPassthruCommand()
        ->setIOChannel($this->getIOChannel())
        ->setCommandChannelFromExecFuture($future)
        ->execute();

      // TODO: Currently, when proxying, we do not write an event log on the
      // proxy. Perhaps we should write a "proxy log". This is not very useful
      // for statistics or auditing, but could be useful for diagnostics.
      // Marking the proxy logs as proxied (and recording devicePHID on all
      // logs) would make differentiating between these use cases easier.

      if (!$err) {
        $this->waitForGitClient();
        return $err;
      }

      // Throw away this service: the request failed and we're treating the
      // failure as persistent, so we don't want to retry another request to
      // the same host.
      array_shift($refs);

      $should_retry = $this->shouldRetryRequest($refs);
      if (!$should_retry) {
        return $err;
      }

      // If we haven't bailed out yet, we'll retry the request with the next
      // service.
    }

    throw new Exception(pht('Reached an unreachable place.'));
  }

  private function didBeginRequest() {
    $this->requestAttempts++;
    return $this;
  }

  private function shouldRetryRequest(array $remaining_refs) {
    $this->requestFailures++;

    if ($this->requestFailures > $this->requestAttempts) {
      throw new Exception(
        pht(
          "Workflow has recorded more failures than attempts; there is a ".
          "missing call to \"didBeginRequest()\".\n"));
    }

    if (!$remaining_refs) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# All available services failed to serve the request, ".
          "giving up.\n"));
      return false;
    }

    $read_len = $this->getIOBytesRead();
    if ($read_len) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# Client already read from service (%s bytes), unable to retry.\n",
          new PhutilNumber($read_len)));
      return false;
    }

    $write_len = $this->getIOBytesWritten();
    if ($write_len) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# Client already wrote to service (%s bytes), unable to retry.\n",
          new PhutilNumber($write_len)));
      return false;
    }

    $this->writeClusterEngineLogMessage(
      pht(
        "# Service request failed, retrying (making attempt %s of %s).\n",
        new PhutilNumber($this->requestAttempts + 1),
        new PhutilNumber($this->requestAttempts + count($remaining_refs))));

    return true;
  }

}
