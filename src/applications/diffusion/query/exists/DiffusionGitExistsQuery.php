<?php

final class DiffusionGitExistsQuery extends DiffusionExistsQuery {

  final protected function executeQuery() {
    $request    = $this->getRequest();
    $repository = $request->getRepository();

    list($err, $merge_base) = $repository->execLocalCommand(
      'cat-file -t %s',
      $request->getCommit()
    );

    return !$err;
  }

}
