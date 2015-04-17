<?php

final class DiffusionUpdateCoverageConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.updatecoverage';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Publish coverage information for a repository.');
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineParamTypes() {
    return array(
      'repositoryPHID' => 'required phid',
      'branch' => 'required string',
      'commit' => 'required string',
      'coverage' => 'required map<string, string>',
    );
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

    $commit_name = $request->getValue('commit');
    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($repository)
      ->withIdentifiers(array($commit_name))
      ->executeOne();
    if (!$commit) {
      throw new Exception(
        pht('No commit exists with identifier "%s".', $commit_name));
    }

    $branch = PhabricatorRepositoryBranch::loadOrCreateBranch(
      $repository->getID(),
      $request->getValue('branch'));

    $coverage = $request->getValue('coverage');
    $path_map = id(new DiffusionPathIDQuery(array_keys($coverage)))
      ->loadPathIDs();

    $conn = $repository->establishConnection('w');

    $sql = array();
    foreach ($coverage as $path => $coverage_info) {
      $sql[] = qsprintf(
        $conn,
        '(%d, %d, %d, %s)',
        $branch->getID(),
        $path_map[$path],
        $commit->getID(),
        $coverage_info);
    }

    $table_name = 'repository_coverage';

    $conn->openTransaction();
      queryfx(
        $conn,
        'DELETE FROM %T WHERE branchID = %d',
        $table_name,
        $branch->getID());

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT INTO %T (branchID, pathID, commitID, coverage) VALUES %Q',
          $table_name,
          $chunk);
      }
    $conn->saveTransaction();
  }

}
