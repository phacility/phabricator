<?php

final class AuditQueryConduitAPIMethod extends AuditConduitAPIMethod {

  public function getAPIMethodName() {
    return 'audit.query';
  }

  public function getMethodDescription() {
    return 'Query audit requests.';
  }

  protected function defineParamTypes() {
    $statuses = array(
      DiffusionCommitQuery::AUDIT_STATUS_ANY,
      DiffusionCommitQuery::AUDIT_STATUS_OPEN,
      DiffusionCommitQuery::AUDIT_STATUS_CONCERN,
      DiffusionCommitQuery::AUDIT_STATUS_ACCEPTED,
      DiffusionCommitQuery::AUDIT_STATUS_PARTIAL,
    );
    $status_const = $this->formatStringConstants($statuses);

    return array(
      'auditorPHIDs'  => 'optional list<phid>',
      'commitPHIDs'   => 'optional list<phid>',
      'status'        => ('optional '.$status_const.
                          ' (default = "audit-status-any")'),
      'offset'        => 'optional int',
      'limit'         => 'optional int (default = 100)',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
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

    // NOTE: These affect the number of commits identified, which is sort of
    // reasonable but means the method may return an arbitrary number of
    // actual audit requests.
    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $commits = $query->execute();

    $auditor_map = array_fuse($auditor_phids);

    $results = array();
    foreach ($commits as $commit) {
      $requests = $commit->getAudits();
      foreach ($requests as $request) {

        // If this audit isn't triggered for one of the requested PHIDs,
        // skip it.
        if ($auditor_map && empty($auditor_map[$request->getAuditorPHID()])) {
          continue;
        }

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
