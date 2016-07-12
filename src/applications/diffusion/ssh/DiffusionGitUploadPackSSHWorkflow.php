<?php

final class DiffusionGitUploadPackSSHWorkflow extends DiffusionGitSSHWorkflow {

  protected function didConstruct() {
    $this->setName('git-upload-pack');
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

    $skip_sync = $this->shouldSkipReadSynchronization();

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Fetch received by \"%s\", forwarding to cluster host.\n",
            $device->getName()));
      }
    } else {
      $command = csprintf('git-upload-pack -- %s', $repository->getLocalPath());
      if (!$skip_sync) {
        $cluster_engine = id(new DiffusionRepositoryClusterEngine())
          ->setViewer($viewer)
          ->setRepository($repository)
          ->setLog($this)
          ->synchronizeWorkingCopyBeforeRead();

        if ($device) {
          $this->writeClusterEngineLogMessage(
            pht(
              "# Cleared to fetch on cluster host \"%s\".\n",
              $device->getName()));
        }
      }
    }
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    $err = $this->newPassthruCommand()
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->execute();

    if (!$err) {
      $this->waitForGitClient();
    }

    return $err;
  }

}
