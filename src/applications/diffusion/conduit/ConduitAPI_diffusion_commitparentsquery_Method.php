<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_commitparentsquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return
      'Commit parent(s) information for a commit in a repository.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $query = DiffusionCommitParentsQuery::newFromDiffusionRequest($drequest);
    $parents = $query->loadParents();
    return $parents;
  }
}
