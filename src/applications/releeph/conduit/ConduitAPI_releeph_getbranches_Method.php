<?php

final class ConduitAPI_releeph_getbranches_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodDescription() {
    return "Return information about all active Releeph branches.";
  }

  public function defineParamTypes() {
    return array(
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();

    $projects = id(new ReleephProject())->loadAllWhere('isActive = 1');

    foreach ($projects as $project) {
      $repository = $project->loadOneRelative(
        id(new PhabricatorRepository()),
        'id',
        'getRepositoryID');

      $branches = $project->loadRelatives(
        id(new ReleephBranch()),
        'releephProjectID',
        'getID',
        'isActive = 1');

      foreach ($branches as $branch) {
        $full_branch_name = $branch->getName();

        $cut_point_commit = $branch->loadOneRelative(
          id(new PhabricatorRepositoryCommit()),
          'phid',
          'getCutPointCommitPHID');

        $results[] = array(
          'project'         => $project->getName(),
          'repository'      => $repository->getCallsign(),
          'branch'          => $branch->getBasename(),
          'fullBranchName'  => $full_branch_name,
          'symbolicName'    => $branch->getSymbolicName(),
          'cutPoint'        => $branch->getCutPointCommitIdentifier(),
        );
      }
    }

    return $results;
  }
}
