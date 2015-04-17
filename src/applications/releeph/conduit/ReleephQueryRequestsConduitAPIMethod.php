<?php

final class ReleephQueryRequestsConduitAPIMethod
  extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releeph.queryrequests';
  }

  public function getMethodDescription() {
    return
      'Return information about all Releeph requests linked to the given ids.';
  }

  protected function defineParamTypes() {
    return array(
      'revisionPHIDs'         => 'optional list<phid>',
      'requestedCommitPHIDs'  => 'optional list<phid>',
    );
  }

  protected function defineReturnType() {
    return 'dict<string, wild>';
  }

  protected function execute(ConduitAPIRequest $conduit_request) {
    $revision_phids = $conduit_request->getValue('revisionPHIDs');
    $requested_commit_phids =
      $conduit_request->getValue('requestedCommitPHIDs');
    $result = array();

    if (!$revision_phids && !$requested_commit_phids) {
      return $result;
    }

    $query = new ReleephRequestQuery();
    $query->setViewer($conduit_request->getUser());

    if ($revision_phids) {
      $query->withRequestedObjectPHIDs($revision_phids);
    } else if ($requested_commit_phids) {
      $query->withRequestedCommitPHIDs($requested_commit_phids);
    }

    $releeph_requests = $query->execute();

    foreach ($releeph_requests as $releeph_request) {
      $branch = $releeph_request->getBranch();

      $request_commit_phid = $releeph_request->getRequestCommitPHID();

      $object = $releeph_request->getRequestedObject();
      if ($object instanceof DifferentialRevision) {
        $object_phid = $object->getPHID();
      } else {
        $object_phid = null;
      }

      $status = $releeph_request->getStatus();
      $status_name = ReleephRequestStatus::getStatusDescriptionFor($status);
      $url = PhabricatorEnv::getProductionURI('/RQ'.$releeph_request->getID());

      $result[] = array(
        'branchBasename' => $branch->getBasename(),
        'branchSymbolic' => $branch->getSymbolicName(),
        'requestID'      => $releeph_request->getID(),
        'revisionPHID'   => $object_phid,
        'status'         => $status,
        'status_name'    => $status_name,
        'url'            => $url,
      );
    }

    return $result;
  }

}
