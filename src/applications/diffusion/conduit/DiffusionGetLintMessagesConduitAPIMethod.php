<?php

final class DiffusionGetLintMessagesConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.getlintmessages';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Get lint messages for existing code.';
  }

  protected function defineParamTypes() {
    return array(
      'arcanistProject' => 'required string',
      'branch'          => 'optional string',
      'commit'          => 'optional string',
      'files'           => 'required list<string>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
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
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($project->getRepositoryID()))
        ->executeOne();
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
