<?php

/**
 * @group conduit
 */
final class ConduitAPI_audit_query_Method extends ConduitAPI_audit_Method {

  public function getMethodDescription() {
    return 'Query audit requests.';
  }

  public function defineParamTypes() {
    $statuses = array(
      'status-any',
      'status-open',
    );
    $status_const = $this->formatStringConstants($statuses);

    return array(
      'auditorPHIDs'  => 'optional list<phid>',
      'commitPHIDs'   => 'optional list<phid>',
      'status'        => 'optional '.$status_const.' (default = "status-any")',
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

    $query = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser());

    $auditor_phids = $request->getValue('auditorPHIDs', array());
    if ($auditor_phids) {
      $query->withAuditorPHIDs($auditor_phids);
    }

    $commit_phids = $request->getValue('commitPHIDs', array());
    if ($commit_phids) {
      $query->withPHIDs($commit_phids);
    }

    $status = $request->getValue(
      'status',
      DiffusionCommitQuery::AUDIT_STATUS_ANY);
    $query->withAuditStatus($status);

    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $commits = $query->execute();

    $results = array();
    foreach ($commits as $commit) {
      $requests = $commit->getAudits();
      foreach ($requests as $request) {
        $results[] = array(
          'id'              => $request->getID(),
          'commitPHID'      => $request->getCommitPHID(),
          'auditorPHID'     => $request->getAuditorPHID(),
          'reasons'         => $request->getAuditReasons(),
          'status'          => $request->getAuditStatus(),
        );
      }
    }

    return $results;
  }


}
