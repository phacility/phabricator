<?php

final class ReleephWorkGetBranchCommitMessageConduitAPIMethod
  extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releephwork.getbranchcommitmessage';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Get a commit message for committing a Releeph branch.');
  }

  protected function defineParamTypes() {
    return array(
      'branchPHID'  => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty string';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getValue('branchPHID')))
      ->executeOne();

    $project = $branch->getProduct();

    $creator_phid = $branch->getCreatedByUserPHID();
    $cut_phid = $branch->getCutPointCommitPHID();

    $phids = array(
      $branch->getPHID(),
      $project->getPHID(),
      $creator_phid,
      $cut_phid,
    );

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($phids)
      ->execute();

    $h_branch = $handles[$branch->getPHID()];
    $h_project = $handles[$project->getPHID()];

    // Not as customizable as a ReleephRequest's commit message. It doesn't
    // really need to be.
    // TODO: Yes it does, see FB-specific stuff below.
    $commit_message = array();
    $commit_message[] = $h_branch->getFullName();
    $commit_message[] = $h_branch->getURI();

    $commit_message[] = pht('Cut Point: %s', $handles[$cut_phid]->getName());

    $cut_point_pr_commit = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere('phid = %s', $cut_phid);
    $cut_point_commit_date = strftime(
      '%Y-%m-%d %H:%M:%S%z',
      $cut_point_pr_commit->getEpoch());
    $commit_message[] = pht('Cut Point Date: %s', $cut_point_commit_date);

    $commit_message[] = pht(
      'Created By: %s',
      $handles[$creator_phid]->getName());

    $project_uri = $project->getURI();
    $commit_message[] = pht(
      'Project: %s',
      $h_project->getName().' '.$project_uri);

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
    $repo = $project->getRepository();
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
