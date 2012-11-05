<?php

final class DiffusionSvnExistsQuery extends DiffusionExistsQuery {

  protected function executeQuery() {
    $request    = $this->getRequest();
    $repository = $request->getRepository();

    list($info) = $repository->execxRemoteCommand(
      'info %s',
      $repository->getRemoteURI()
    );

    $matches = null;
    $exists  = false;
    if (preg_match('/^Revision: (\d+)$/m', $info, $matches)) {
      $base_revision = $matches[1];
      $exists = $base_revision >= $request->getCommit();
    }

    return $exists;
  }

}
