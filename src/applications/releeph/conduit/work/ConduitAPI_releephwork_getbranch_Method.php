<?php

final class ConduitAPI_releephwork_getbranch_Method
  extends ConduitAPI_releeph_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Return information to help checkout / cut a Releeph branch.";
  }

  public function defineParamTypes() {
    return array(
      'branchPHID'  => 'required string',
    );
  }

  public function defineReturnType() {
    return 'dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $branch = id(new ReleephBranch())
      ->loadOneWhere('phid = %s', $request->getValue('branchPHID'));

    $cut_phid = $branch->getCutPointCommitPHID();
    $phids = array($cut_phid);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $project = $branch->loadReleephProject();
    $repo = $project->loadPhabricatorRepository();

    return array(
      'branchName'      => $branch->getName(),
      'branchPHID'      => $branch->getPHID(),
      'vcsType'         => $repo->getVersionControlSystem(),
      'cutCommitID'     => $branch->getCutPointCommitIdentifier(),
      'cutCommitName'   => $handles[$cut_phid]->getName(),
      'creatorPHID'     => $branch->getCreatedByUserPHID(),
      'trunk'           => $project->getTrunkBranch(),
    );
  }

}
