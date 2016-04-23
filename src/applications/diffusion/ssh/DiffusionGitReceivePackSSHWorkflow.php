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

    // This is a write, and must have write access.
    $this->requireWriteAccess();

    if ($this->shouldProxy()) {
      $command = $this->getProxyCommand();
      $did_synchronize = false;
    } else {
      $command = csprintf('git-receive-pack %s', $repository->getLocalPath());

      $did_synchronize = true;
      $viewer = $this->getUser();
      $repository->synchronizeWorkingCopyBeforeWrite($viewer);
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
      $repository->synchronizeWorkingCopyAfterWrite();
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
