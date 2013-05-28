<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_commitbranchesquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return 'Determine what branches contain a commit in a repository.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $request->getValue('commit');

    list($contains) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev --contains %s',
      $commit);

    return DiffusionGitBranch::parseRemoteBranchOutput(
      $contains,
      DiffusionBranchInformation::DEFAULT_GIT_REMOTE);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $request->getValue('commit');

    list($contains) = $repository->execxLocalCommand(
      'log --template %s --limit 1 --rev %s --',
      '{branch}',
      $commit);

    return array(
      trim($contains) => $commit,
    );

  }
}
