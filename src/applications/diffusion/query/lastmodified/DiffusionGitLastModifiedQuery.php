<?php

final class DiffusionGitLastModifiedQuery extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($hash) = $repository->execxLocalCommand(
      'log -n1 --format=%%H %s -- %s',
      $drequest->getCommit(),
      $drequest->getPath());
    $hash = trim($hash);

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $hash);

    if ($commit) {
      $commit_data = $commit->loadCommitData();
    } else {
      $commit_data = null;
    }

    return array($commit, $commit_data);
  }

}
