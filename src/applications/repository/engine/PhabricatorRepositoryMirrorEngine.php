<?php

/**
 * Pushes a repository to its mirrors.
 */
final class PhabricatorRepositoryMirrorEngine
  extends PhabricatorRepositoryEngine {

  public function pushToMirrors() {
    $repository = $this->getRepository();

    if (!$repository->canMirror()) {
      return;
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $this->log(
        pht('Phabricator is running in silent mode; declining to mirror.'));
      return;
    }

    $mirrors = id(new PhabricatorRepositoryMirrorQuery())
      ->setViewer($this->getViewer())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    $exceptions = array();
    foreach ($mirrors as $mirror) {
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
          $repository->getCallsign()),
        $exceptions);
    }
  }

  private function pushRepositoryToMirror(
    PhabricatorRepository $repository,
    PhabricatorRepositoryMirror $mirror) {

    // TODO: This is a little bit janky, but we don't have first-class
    // infrastructure for running remote commands against an arbitrary remote
    // right now. Just make an emphemeral copy of the repository and muck with
    // it a little bit. In the medium term, we should pull this command stuff
    // out and use it here and for "Land to ...".

    $proxy = clone $repository;
    $proxy->makeEphemeral();

    $proxy->setDetail('hosting-enabled', false);
    $proxy->setDetail('remote-uri', $mirror->getRemoteURI());
    $proxy->setCredentialPHID($mirror->getCredentialPHID());

    $this->log(pht('Pushing to remote "%s"...', $mirror->getRemoteURI()));

    if ($proxy->isGit()) {
      $this->pushToGitRepository($proxy);
    } else if ($proxy->isHg()) {
      $this->pushToHgRepository($proxy);
    } else {
      throw new Exception(pht('Unsupported VCS!'));
    }
  }

  private function pushToGitRepository(
    PhabricatorRepository $proxy) {

    $future = $proxy->getRemoteCommandFuture(
      'push --verbose --mirror -- %P',
      $proxy->getRemoteURIEnvelope());

    $future
      ->setCWD($proxy->getLocalPath())
      ->resolvex();
  }

  private function pushToHgRepository(
    PhabricatorRepository $proxy) {

    $future = $proxy->getRemoteCommandFuture(
      'push --verbose --rev tip -- %P',
      $proxy->getRemoteURIEnvelope());

    try {
      $future
        ->setCWD($proxy->getLocalPath())
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
