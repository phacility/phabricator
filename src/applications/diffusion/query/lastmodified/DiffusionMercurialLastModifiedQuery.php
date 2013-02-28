<?php

final class DiffusionMercurialLastModifiedQuery
  extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    list($hash) = $repository->execxLocalCommand(
      'log --template %s --limit 1 --rev %s:0 -- %s',
      '{node}',
      $drequest->getCommit(),
      nonempty(ltrim($path, '/'), '.'));

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
