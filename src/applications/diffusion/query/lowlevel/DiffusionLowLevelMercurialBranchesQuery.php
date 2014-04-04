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
    $stdout = PhabricatorRepository::filterMercurialDebugOutput($stdout);

    $branches = array();

    $lines = ArcanistMercurialParser::parseMercurialBranches($stdout);
    foreach ($lines as $name => $spec) {
      $branches[] = id(new DiffusionRepositoryRef())
        ->setShortName($name)
        ->setCommitIdentifier($spec['rev']);
    }

    return $branches;
  }

}
