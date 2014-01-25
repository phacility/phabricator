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

    $mirrors = id(new PhabricatorRepositoryMirrorQuery())
      ->setViewer($this->getViewer())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    // TODO: This is a little bit janky, but we don't have first-class
    // infrastructure for running remote commands against an arbitrary remote
    // right now. Just make an emphemeral copy of the repository and muck with
    // it a little bit. In the medium term, we should pull this command stuff
    // out and use it here and for "Land to ...".

    $proxy = clone $repository;
    $proxy->makeEphemeral();

    $proxy->setDetail('hosting-enabled', false);
    foreach ($mirrors as $mirror) {
      $proxy->setDetail('remote-uri', $mirror->getRemoteURI());
      $proxy->setCredentialPHID($mirror->getCredentialPHID());

      $this->log(pht('Pushing to remote "%s"...', $mirror->getRemoteURI()));

      if (!$proxy->isGit()) {
        throw new Exception('Unsupported VCS!');
      }

      $future = $proxy->getRemoteCommandFuture(
        'push --verbose --mirror -- %P',
        $proxy->getRemoteURIEnvelope());

      $future
        ->setCWD($proxy->getLocalPath())
        ->resolvex();
    }
  }

}
