<?php

/**
 * Pushes a repository to its mirrors.
 */
final class PhabricatorRepositoryMirrorEngine
  extends PhabricatorRepositoryEngine {

  public function pushToMirrors() {
    $viewer = $this->getViewer();
    $repository = $this->getRepository();

    if (!$repository->canMirror()) {
      return;
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $this->log(
        pht('Phabricator is running in silent mode; declining to mirror.'));
      return;
    }

    $uris = id(new PhabricatorRepositoryURIQuery())
      ->setViewer($viewer)
      ->withRepositories(array($repository))
      ->execute();

    $io_mirror = PhabricatorRepositoryURI::IO_MIRROR;

    $exceptions = array();
    foreach ($uris as $mirror) {
      if ($mirror->getIsDisabled()) {
        continue;
      }

      $io_type = $mirror->getEffectiveIOType();
      if ($io_type != $io_mirror) {
        continue;
      }

      try {
        $this->pushRepositoryToMirror($repository, $mirror);
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    if ($exceptions) {
      throw new PhutilAggregateException(
        pht(
          'Exceptions occurred while mirroring the "%s" repository.',
          $repository->getDisplayName()),
        $exceptions);
    }
  }

  private function pushRepositoryToMirror(
    PhabricatorRepository $repository,
    PhabricatorRepositoryURI $mirror_uri) {

    $this->log(
      pht(
        'Pushing to remote "%s"...',
        $mirror_uri->getEffectiveURI()));

    if ($repository->isGit()) {
      $this->pushToGitRepository($repository, $mirror_uri);
    } else if ($repository->isHg()) {
      $this->pushToHgRepository($repository, $mirror_uri);
    } else {
      throw new Exception(pht('Unsupported VCS!'));
    }
  }

  private function pushToGitRepository(
    PhabricatorRepository $repository,
    PhabricatorRepositoryURI $mirror_uri) {

    $argv = array(
      'push --verbose --mirror -- %P',
      $mirror_uri->getURIEnvelope(),
    );

    $future = $mirror_uri->newCommandEngine()
      ->setArgv($argv)
      ->newFuture();

    $future
      ->setCWD($repository->getLocalPath())
      ->resolvex();
  }

  private function pushToHgRepository(
    PhabricatorRepository $repository,
    PhabricatorRepositoryURI $mirror_uri) {

    $argv = array(
      'push --verbose --rev tip -- %P',
      $mirror_uri->getURIEnvelope(),
    );

    $future = $mirror_uri->newCommandEngine()
      ->setArgv($argv)
      ->newFuture();

    try {
      $future
        ->setCWD($repository->getLocalPath())
        ->resolvex();
    } catch (CommandException $ex) {
      if (preg_match('/no changes found/', $ex->getStdOut())) {
        // mercurial says nothing changed, but that's good
      } else {
        throw $ex;
      }
    }
  }

}
