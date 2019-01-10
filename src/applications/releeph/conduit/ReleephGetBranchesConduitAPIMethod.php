<?php

final class ReleephGetBranchesConduitAPIMethod extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releeph.getbranches';
  }

  public function getMethodDescription() {
    return pht('Return information about all active Releeph branches.');
  }

  protected function defineParamTypes() {
    return array(
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();

    $projects = id(new ReleephProductQuery())
      ->setViewer($request->getUser())
      ->withActive(1)
      ->execute();

    foreach ($projects as $project) {
      $repository = $project->getRepository();

      $branches = id(new ReleephBranch())->loadAllWhere(
        'releephProjectID = %d AND isActive = 1',
        $project->getID());

      foreach ($branches as $branch) {
        $full_branch_name = $branch->getName();

        $cut_point_commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
          'phid = %s',
          $branch->getCutPointCommitPHID());

        $results[] = array(
          'project'         => $project->getName(),
          'repository'      => $repository->getCallsign(),
          'branch'          => $branch->getBasename(),
          'fullBranchName'  => $full_branch_name,
          'symbolicName'    => $branch->getSymbolicName(),
          'cutPoint'        => $cut_point_commit->getCommitIdentifier(),
        );
      }
    }

    return $results;
  }

}
