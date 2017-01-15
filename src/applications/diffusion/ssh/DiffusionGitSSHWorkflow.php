<?php

abstract class DiffusionGitSSHWorkflow
  extends DiffusionSSHWorkflow
  implements DiffusionRepositoryClusterEngineLogInterface {

  protected function writeError($message) {
    // Git assumes we'll add our own newlines.
    return parent::writeError($message."\n");
  }

  public function writeClusterEngineLogMessage($message) {
    parent::writeError($message);
    $this->getErrorChannel()->update();
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

}
