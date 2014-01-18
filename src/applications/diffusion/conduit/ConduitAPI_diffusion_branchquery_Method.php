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

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsOriginBranch(true)
      ->execute();

    return $this->processBranchRefs($request, $refs);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $refs = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository)
      ->execute();

    return $this->processBranchRefs($request, $refs);
  }

  private function processBranchRefs(ConduitAPIRequest $request, array $refs) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    foreach ($refs as $key => $ref) {
      if (!$repository->shouldTrackBranch($ref->getShortName())) {
        unset($refs[$key]);
      }
    }

    // NOTE: We can't apply the offset or limit until here, because we may have
    // filtered untrackable branches out of the result set.

    if ($offset) {
      $refs = array_slice($refs, $offset);
    }

    if ($limit) {
      $refs = array_slice($refs, 0, $limit);
    }

    return mpull($refs, 'toDictionary');
  }
}
