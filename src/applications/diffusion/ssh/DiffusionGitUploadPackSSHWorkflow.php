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

    $skip_sync = $this->shouldSkipReadSynchronization();

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();
    } else {
      $command = csprintf('git-upload-pack -- %s', $repository->getLocalPath());
      if (!$skip_sync) {
        $repository->synchronizeWorkingCopyBeforeRead();
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
