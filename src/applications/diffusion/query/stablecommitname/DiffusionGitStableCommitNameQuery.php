<?php

final class DiffusionGitStableCommitNameQuery
extends DiffusionStableCommitNameQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();
    $branch = $this->getBranch();
    list($stdout) = $repository->execxLocalCommand(
      'rev-parse --verify %s',
      $branch);

    $commit = trim($stdout);
    return substr($commit, 0, 16);
  }
}
