<?php

final class DiffusionMercurialExistsQuery extends DiffusionExistsQuery {

  protected function executeQuery() {
    $request    = $this->getRequest();
    $repository = $request->getRepository();

    list($err, $stdout) = $repository->execLocalCommand(
      'id --rev %s',
      $request->getCommit());

    return !$err;

  }
}
