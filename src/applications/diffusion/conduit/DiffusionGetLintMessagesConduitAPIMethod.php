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
    return pht('Get lint messages for existing code.');
  }

  protected function defineParamTypes() {
    return array(
      'repositoryPHID' => 'required phid',
      'branch'         => 'required string',
      'commit'         => 'optional string',
      'files'          => 'required list<string>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $repository_phid = $request->getValue('repositoryPHID');
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($repository_phid))
      ->executeOne();

    if (!$repository) {
      throw new Exception(
        pht('No repository exists with PHID "%s".', $repository_phid));
    }

    $branch_name = $request->getValue('branch');
    if ($branch_name == '') {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($repository->getID()))
        ->executeOne();
      $branch_name = $repository->getDefaultArcanistBranch();
    }

    $branch = id(new PhabricatorRepositoryBranch())->loadOneWhere(
      'repositoryID = %d AND name = %s',
      $repository->getID(),
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
