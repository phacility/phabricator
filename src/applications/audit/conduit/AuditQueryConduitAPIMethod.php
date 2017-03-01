<?php

final class AuditQueryConduitAPIMethod extends AuditConduitAPIMethod {

  const AUDIT_LEGACYSTATUS_ANY       = 'audit-status-any';
  const AUDIT_LEGACYSTATUS_OPEN      = 'audit-status-open';
  const AUDIT_LEGACYSTATUS_CONCERN   = 'audit-status-concern';
  const AUDIT_LEGACYSTATUS_ACCEPTED  = 'audit-status-accepted';
  const AUDIT_LEGACYSTATUS_PARTIAL   = 'audit-status-partial';

  public function getAPIMethodName() {
    return 'audit.query';
  }

  public function getMethodDescription() {
    return pht('Query audit requests.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "diffusion.commit.search" instead.');
  }

  protected function defineParamTypes() {
    $statuses = array(
      self::AUDIT_LEGACYSTATUS_ANY,
      self::AUDIT_LEGACYSTATUS_OPEN,
      self::AUDIT_LEGACYSTATUS_CONCERN,
      self::AUDIT_LEGACYSTATUS_ACCEPTED,
      self::AUDIT_LEGACYSTATUS_PARTIAL,
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
      ->setViewer($request->getUser())
      ->needAuditRequests(true);

    $auditor_phids = $request->getValue('auditorPHIDs', array());
    if ($auditor_phids) {
      $query->withAuditorPHIDs($auditor_phids);
    }

    $commit_phids = $request->getValue('commitPHIDs', array());
    if ($commit_phids) {
      $query->withPHIDs($commit_phids);
    }

    $status_map = array(
      self::AUDIT_LEGACYSTATUS_OPEN => array(
        PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT,
        PhabricatorAuditCommitStatusConstants::CONCERN_RAISED,
      ),
      self::AUDIT_LEGACYSTATUS_CONCERN => array(
        PhabricatorAuditCommitStatusConstants::CONCERN_RAISED,
      ),
      self::AUDIT_LEGACYSTATUS_ACCEPTED => array(
        PhabricatorAuditCommitStatusConstants::FULLY_AUDITED,
      ),
      self::AUDIT_LEGACYSTATUS_PARTIAL => array(
        PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED,
      ),
    );

    $status = $request->getValue('status');
    if (isset($status_map[$status])) {
      $query->withStatuses($status_map[$status]);
    }

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
          'reasons'         => array(),
          'status'          => $request->getAuditStatus(),
        );
      }
    }

    return $results;
  }

}
