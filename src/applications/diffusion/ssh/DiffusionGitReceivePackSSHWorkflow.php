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
      $is_proxy = true;
    } else {
      $command = csprintf('git-receive-pack %s', $repository->getLocalPath());
      $is_proxy = false;

      $repository->synchronizeWorkingCopyBeforeWrite();
    }
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    $err = $this->newPassthruCommand()
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->execute();

    if (!$err) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
      $this->waitForGitClient();
    }

    if (!$is_proxy) {
      $repository->synchronizeWorkingCopyAfterWrite();
    }

    return $err;
  }

}
