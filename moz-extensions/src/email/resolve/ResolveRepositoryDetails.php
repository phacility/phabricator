<?php


class ResolveRepositoryDetails {
  public function resolveRepoName(string $PHID): string {
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($PHID))
      ->executeOne();
    return $repository->getName();
  }

  public function resolveCommit(string $commitPHID): PhabricatorRepositoryCommit {
    return id(new DiffusionCommitQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($commitPHID))
      ->executeOne();
  }

  public function resolveHgLink(PhabricatorRepositoryCommit $commit): ?string {
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($commit->getRepositoryID()))
      ->executeOne();

    if ($repository->getVersionControlSystem() != 'hg') {
      return null;
    }

    $repositoryURIs = (new PhabricatorRepositoryURIQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->execute();

    foreach ($repositoryURIs as $repositoryURI) {
      if ($repositoryURI->getIoType() == 'observe') {
        return "{$repositoryURI->getUri()}/rev/{$commit->getCommitIdentifier()}";
      }
    }

    return null;
  }
}