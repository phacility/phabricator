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
    $repository = $this->getRepository();
    $viewer = $this->getUser();
    $device = AlmanacKeys::getLiveDevice();

    // This is a write, and must have write access.
    $this->requireWriteAccess();

    $cluster_engine = id(new DiffusionRepositoryClusterEngine())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->setLog($this);

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();
      $did_synchronize = false;

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Push received by \"%s\", forwarding to cluster host.\n",
            $device->getName()));
      }
    } else {
      $command = csprintf('git-receive-pack %s', $repository->getLocalPath());
      $did_synchronize = true;
      $cluster_engine->synchronizeWorkingCopyBeforeWrite();

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Ready to receive on cluster host \"%s\".\n",
            $device->getName()));
      }
    }

    $caught = null;
    try {
      $err = $this->executeRepositoryCommand($command);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    // We've committed the write (or rejected it), so we can release the lock
    // without waiting for the client to receive the acknowledgement.
    if ($did_synchronize) {
      $cluster_engine->synchronizeWorkingCopyAfterWrite();
    }

    if ($caught) {
      throw $caught;
    }

    if (!$err) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
      $this->waitForGitClient();
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

}
