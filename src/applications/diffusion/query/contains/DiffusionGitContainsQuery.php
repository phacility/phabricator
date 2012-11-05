<?php

final class DiffusionGitContainsQuery extends DiffusionContainsQuery {

  final public function executeQuery() {
    $request = $this->getRequest();
    $repository = $request->getRepository();

    list($contains) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev --contains %s',
      $request->getCommit());

    return DiffusionGitBranchQuery::parseGitRemoteBranchOutput(
      $contains,
      DiffusionBranchInformation::DEFAULT_GIT_REMOTE);
  }

}
