<?php

final class DiffusionSSHGitUploadPackWorkflow
  extends DiffusionSSHGitWorkflow {

  public function didConstruct() {
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
    $args = $this->getArgs();
    $path = head($args->getArg('dir'));
    $repository = $this->loadRepository($path);

    $command = csprintf('git-upload-pack -- %s', $repository->getLocalPath());
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
