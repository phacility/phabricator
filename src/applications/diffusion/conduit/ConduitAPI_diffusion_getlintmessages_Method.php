<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_getlintmessages_Method
  extends ConduitAPI_diffusion_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Get lint messages for existing code.";
  }

  public function defineParamTypes() {
    return array(
      'arcanistProject' => 'required string',
      'branch'          => 'optional string',
      'commit'          => 'optional string',
      'files'           => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
      'name = %s',
      $request->getValue('arcanistProject'));
    if (!$project || !$project->getRepositoryID()) {
      return array();
    }

    $branch_name = $request->getValue('branch');
    if ($branch_name == '') {
      $repository = id(new PhabricatorRepository())
        ->load($project->getRepositoryID());
      $branch_name = $repository->getDefaultArcanistBranch();
    }

    $branch = id(new PhabricatorRepositoryBranch())->loadOneWhere(
      'repositoryID = %d AND name = %s',
      $project->getRepositoryID(),
      $branch_name);
    if (!$branch || !$branch->getLintCommit()) {
      return array();
    }

    $lint_messages = queryfx_all(
      $branch->establishConnection('r'),
      'SELECT path, line, code FROM %T WHERE branchID = %d AND path IN (%Ls)',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $request->getValue('files'));

    // TODO: Compare commit identifiers of individual files like in
    // DiffusionBrowseFileController::loadLintMessages().

    return $lint_messages;
  }

}
