<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_stablecommitnamequery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function __construct() {
    $this->setShouldCreateDiffusionRequest(false);
  }

  public function getMethodDescription() {
    return
      'Identifies the latest commit in a repository. Repositories with '.
      'branch support must specify which branch to look at.';
  }

  public function defineReturnType() {
    return 'string';
  }

  protected function defineCustomParamTypes() {
    return array(
      'branch' => 'required string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $repository = $this->getRepository($request);
    $query = DiffusionStableCommitNameQuery::newFromRepository($repository);
    $query->setBranch($request->getValue('branch'));
    return $query->load();
  }
}
