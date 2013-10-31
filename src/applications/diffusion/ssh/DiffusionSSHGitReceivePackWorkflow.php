<?php

final class DiffusionSSHGitReceivePackWorkflow
  extends DiffusionSSHGitWorkflow {

  public function didConstruct() {
    $this->setName('git-receive-pack');
    $this->setArguments(
      array(
        array(
          'name'      => 'dir',
          'wildcard'  => true,
        ),
      ));
  }

  public function isReadOnly() {
    return false;
  }

  public function getRequestPath() {
    $args = $this->getArgs();
    return head($args->getArg('dir'));
  }

  protected function executeRepositoryOperations(
    PhabricatorRepository $repository) {
    $future = new ExecFuture(
      'git-receive-pack %s',
      $repository->getLocalPath());
    $err = $this->passthruIO($future);

    if (!$err) {
      $repository->writeStatusMessage(
        PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
        PhabricatorRepositoryStatusMessage::CODE_OKAY);
    }

    return $err;
  }

}
