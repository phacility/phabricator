<?php

final class DiffusionSvnStableCommitNameQuery
extends DiffusionStableCommitNameQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();

    $commit = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere(
        'repositoryID = %d ORDER BY epoch DESC LIMIT 1',
        $repository->getID());
    if ($commit) {
      $stable_commit_name = $commit->getCommitIdentifier();
    } else {
      // For new repositories, we may not have parsed any commits yet. Call
      // the stable commit "1" and avoid fataling.
      $stable_commit_name = 1;
    }

    return $stable_commit_name;
  }
}
