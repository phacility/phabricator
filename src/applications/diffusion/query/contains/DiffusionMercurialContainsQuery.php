<?php

final class DiffusionMercurialContainsQuery extends DiffusionContainsQuery {

  protected function executeQuery() {
    $request = $this->getRequest();
    $repository = $request->getRepository();
    list($contains) = $repository->execxLocalCommand(
      'log --template %s --limit 1 --rev %s --',
      '{branch}',
      $request->getCommit());

    return array(
      trim($contains) => $request->getCommit(),
    );
  }

}
