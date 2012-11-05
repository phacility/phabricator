<?php

final class DiffusionGitCommitParentsQuery
  extends DiffusionCommitParentsQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log -n 1 --format=%s %s',
      '%P',
      $drequest->getStableCommitName());

    $hashes = preg_split('/\s+/', trim($stdout));

    return self::loadCommitsByIdentifiers($hashes);
  }
}
