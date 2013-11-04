<?php

/**
 * Execute and parse a low-level Mercurial branches query using `hg branches`.
 */
final class DiffusionLowLevelMercurialBranchesQuery
  extends DiffusionLowLevelQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();

    // NOTE: `--debug` gives us 40-character hashes.
    list($stdout) = $repository->execxLocalCommand(
      '--debug branches');

    $branches = array();

    $lines = ArcanistMercurialParser::parseMercurialBranches($stdout);
    foreach ($lines as $name => $spec) {
      $branches[] = id(new DiffusionBranchInformation())
        ->setName($name)
        ->setHeadCommitIdentifier($spec['rev']);
    }

    return $branches;
  }

}
