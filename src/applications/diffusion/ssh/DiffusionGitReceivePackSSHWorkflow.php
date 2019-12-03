<?php

final class DiffusionGitReceivePackSSHWorkflow extends DiffusionGitSSHWorkflow {

  protected function didConstruct() {
    $this->setName('git-receive-pack');
    $this->setArguments(
      array(
        array(
          'name'      => 'dir',
          'wildcard'  => true,
        ),
      ));
  }

  protected function executeRepositoryOperations() {
    // This is a write, and must have write access.
    $this->requireWriteAccess();

    $is_proxy = $this->shouldProxy();
    if ($is_proxy) {
      return $this->executeRepositoryProxyOperations($for_write = true);
    }

    $host_wait_start = microtime(true);

    $repository = $this->getRepository();
    $viewer = $this->getSSHUser();
    $device = AlmanacKeys::getLiveDevice();

    $cluster_engine = id(new DiffusionRepositoryClusterEngine())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->setLog($this);

    $command = csprintf('git-receive-pack %s', $repository->getLocalPath());
    $cluster_engine->synchronizeWorkingCopyBeforeWrite();

    if ($device) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# Ready to receive on cluster host \"%s\".\n",
          $device->getName()));
    }

    $log = $this->newProtocolLog($is_proxy);
    if ($log) {
      $this->setProtocolLog($log);
      $log->didStartSession($command);
    }

    $caught = null;
    try {
      $err = $this->executeRepositoryCommand($command);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    if ($log) {
      $log->didEndSession();
    }

    // We've committed the write (or rejected it), so we can release the lock
    // without waiting for the client to receive the acknowledgement.
    $cluster_engine->synchronizeWorkingCopyAfterWrite();

    if ($caught) {
      throw $caught;
    }

    if (!$err) {
      $this->waitForGitClient();

      // When a repository is clustered, we reach this cleanup code on both
      // the proxy and the actual final endpoint node. Don't do more cleanup
      // or logging than we need to.
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);

      $host_wait_end = microtime(true);

      $this->updatePushLogWithTimingInformation(
        $this->getClusterEngineLogProperty('writeWait'),
        $this->getClusterEngineLogProperty('readWait'),
        ($host_wait_end - $host_wait_start));
    }

    return $err;
  }

  private function executeRepositoryCommand($command) {
    $repository = $this->getRepository();
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    return $this->newPassthruCommand()
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->execute();
  }

  private function updatePushLogWithTimingInformation(
    $write_wait,
    $read_wait,
    $host_wait) {

    if ($write_wait !== null) {
      $write_wait = (int)(1000000 * $write_wait);
    }

    if ($read_wait !== null) {
      $read_wait = (int)(1000000 * $read_wait);
    }

    if ($host_wait !== null) {
      $host_wait = (int)(1000000 * $host_wait);
    }

    $identifier = $this->getRequestIdentifier();

    $event = new PhabricatorRepositoryPushEvent();
    $conn = $event->establishConnection('w');

    queryfx(
      $conn,
      'UPDATE %T SET writeWait = %nd, readWait = %nd, hostWait = %nd
        WHERE requestIdentifier = %s',
      $event->getTableName(),
      $write_wait,
      $read_wait,
      $host_wait,
      $identifier);
  }

}
