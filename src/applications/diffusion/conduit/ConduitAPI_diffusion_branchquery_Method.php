<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_branchquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return 'Determine what branches exist for a repository.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'limit' => 'optional int',
      'offset' => 'optional int'
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsOriginBranch(true)
      ->execute();

    $branches = array();
    foreach ($refs as $ref) {
      $branch = id(new DiffusionBranchInformation())
        ->setName($ref->getShortName())
        ->setHeadCommitIdentifier($ref->getCommitIdentifier());

      if (!$repository->shouldTrackBranch($branch->getName())) {
        continue;
      }

      $branches[] = $branch->toDictionary();
    }

    // NOTE: We can't apply the offset or limit until here, because we may have
    // filtered untrackable branches out of the result set.

    if ($offset) {
      $branches = array_slice($branches, $offset);
    }

    if ($limit) {
      $branches = array_slice($branches, 0, $limit);
    }

    return $branches;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    list($stdout) = $repository->execxLocalCommand(
      '--debug branches');
    $branch_info = ArcanistMercurialParser::parseMercurialBranches($stdout);

    $branches = array();
    foreach ($branch_info as $name => $info) {
      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($info['rev']);
      $branches[] = $branch->toDictionary();
    }

    if ($offset) {
      $branches = array_slice($branches, $offset);
    }

    if ($limit) {
      $branches = array_slice($branches, 0, $limit);
    }

    return $branches;
  }
}
