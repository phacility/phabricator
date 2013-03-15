<?php

final class ConduitAPI_releephwork_getbranchcommitmessage_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Get a commit message for committing a Releeph branch.";
  }

  public function defineParamTypes() {
    return array(
      'branchPHID'  => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty string';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $branch = id(new ReleephBranch())
      ->loadOneWhere('phid = %s', $request->getValue('branchPHID'));

    $project = $branch->loadReleephProject();

    $creator_phid = $branch->getCreatedByUserPHID();
    $cut_phid = $branch->getCutPointCommitPHID();

    $phids = array(
      $branch->getPHID(),
      $project->getPHID(),
      $creator_phid,
      $cut_phid,
    );

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $h_branch = $handles[$branch->getPHID()];
    $h_project = $handles[$project->getPHID()];

    // Not as customizable as a ReleephRequest's commit message.  It doesn't
    // really need to be.
    $commit_message = array();
    $commit_message[] = $h_branch->getFullName();
    $commit_message[] = $h_branch->getURI();

    $commit_message[] = "Cut Point: ".$handles[$cut_phid]->getName();

    $cut_point_pr_commit = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere('phid = %s', $cut_phid);
    $cut_point_commit_date = strftime(
      '%Y-%m-%d %H:%M:%S%z',
      $cut_point_pr_commit->getEpoch());
    $commit_message[] = "Cut Point Date: {$cut_point_commit_date}";

    $commit_message[] = "Created By: ".$handles[$creator_phid]->getName();

    $project_uri = $project->getURI();
    $commit_message[] = "Project: ".$h_project->getName()." ".$project_uri;

    /**
     * Required for 090-limit_new_branch_creations.sh in
     * admin/scripts/git/hosting/hooks/update.d (in the E repo):
     *
     *   http://fburl.com/2372545
     *
     * The commit message must have a line saying:
     *
     *   @new-branch: <branch-name>
     *
     */
    $repo = $project->loadPhabricatorRepository();
    switch ($repo->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $commit_message[] = sprintf(
          '@new-branch: %s',
          $branch->getName());
        break;
    }

    return implode("\n\n", $commit_message);
  }

}
