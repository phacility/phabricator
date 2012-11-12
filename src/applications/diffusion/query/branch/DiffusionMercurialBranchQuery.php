<?php

final class DiffusionMercurialBranchQuery extends DiffusionBranchQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      '--debug branches');
    $branch_info = ArcanistMercurialParser::parseMercurialBranches($stdout);

    $branches = array();
    foreach ($branch_info as $name => $info) {
      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($info['rev']);
      $branches[] = $branch;
    }

    if ($this->getOffset()) {
      $branches = array_slice($branches, $this->getOffset());
    }

    if ($this->getLimit()) {
      $branches = array_slice($branches, 0, $this->getLimit());
    }

    return $branches;
  }

}
