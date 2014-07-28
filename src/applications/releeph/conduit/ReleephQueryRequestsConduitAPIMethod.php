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

  public function defineParamTypes() {
    return array(
      'revisionPHIDs'         => 'optional list<phid>',
      'requestedCommitPHIDs'  => 'optional list<phid>'
    );
  }

  public function defineReturnType() {
    return 'dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
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

    $releephRequests = $query->execute();

    foreach ($releephRequests as $releephRequest) {
      $branch = $releephRequest->getBranch();

      $request_commit_phid = $releephRequest->getRequestCommitPHID();

      $object = $releephRequest->getRequestedObject();
      if ($object instanceof DifferentialRevision) {
        $object_phid = $object->getPHID();
      }  else {
        $object_phid = null;
      }

      $status = $releephRequest->getStatus();
      $statusName = ReleephRequestStatus::getStatusDescriptionFor($status);
      $url = PhabricatorEnv::getProductionURI('/RQ'.$releephRequest->getID());

      $result[] = array(
        'branchBasename' => $branch->getBasename(),
        'branchSymbolic' => $branch->getSymbolicName(),
        'requestID'      => $releephRequest->getID(),
        'revisionPHID'   => $object_phid,
        'status'         => $status,
        'statusName'     => $statusName,
        'url'            => $url,
      );
    }

    return $result;
  }

}
