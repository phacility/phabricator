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
        pht('This software is running in silent mode; declining to mirror.'));
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

    // See T5965. Test if we have any refs to mirror. If we have nothing, git
    // will exit with an error ("No refs in common and none specified; ...")
    // when we run "git push --mirror".

    // If we don't have any refs, we just bail out. (This is arguably sort of
    // the wrong behavior: to mirror an empty repository faithfully we should
    // delete everything in the remote.)

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref --count 1 --');
    if (!strlen($stdout)) {
      return;
    }

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
      if (preg_match('/no changes found/', $ex->getStdout())) {
        // mercurial says nothing changed, but that's good
      } else {
        throw $ex;
      }
    }
  }

}
