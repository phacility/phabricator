<?php

/**
 * @group conduit
 */
final class ConduitAPI_audit_query_Method extends ConduitAPI_audit_Method {

  public function getMethodDescription() {
    return "Query audit requests.";
  }

  public function defineParamTypes() {
    return array(
      'auditorPHIDs'  => 'optional list<phid>',
      'commitPHIDs'   => 'optional list<phid>',
      'status'        => 'optional enum<"status-any", "status-open"> '.
                         '(default = "status-any")',
      'offset'        => 'optional int',
      'limit'         => 'optional int (default = 100)',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $query = new PhabricatorAuditQuery();

    $auditor_phids = $request->getValue('auditorPHIDs', array());
    if ($auditor_phids) {
      $query->withAuditorPHIDs($auditor_phids);
    }

    $commit_phids = $request->getValue('commitPHIDs', array());
    if ($commit_phids) {
      $query->withCommitPHIDs($commit_phids);
    }

    $status = $request->getValue('status', PhabricatorAuditQuery::STATUS_ANY);
    $query->withStatus($status);

    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $requests = $query->execute();

    $results = array();
    foreach ($requests as $request) {
      $results[] = array(
        'id'              => $request->getID(),
        'commitPHID'      => $request->getCommitPHID(),
        'auditorPHID'     => $request->getAuditorPHID(),
        'reasons'         => $request->getAuditReasons(),
        'status'          => $request->getAuditStatus(),
      );
    }

    return $results;
  }


}
