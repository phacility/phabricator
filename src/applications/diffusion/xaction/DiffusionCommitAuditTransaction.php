<?php

abstract class DiffusionCommitAuditTransaction
  extends DiffusionCommitActionTransaction {

  protected function getCommitActionGroupKey() {
    return DiffusionCommitEditEngine::ACTIONGROUP_AUDIT;
  }

  public function generateOldValue($object) {
    return false;
  }

  protected function isViewerAnyAuditor(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {
    return ($this->getViewerAuditStatus($commit, $viewer) !== null);
  }

  protected function isViewerAnyActiveAuditor(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {

    // This omits various inactive states like "Resigned" and "Not Required".
    $active = array(
      PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
      PhabricatorAuditStatusConstants::CONCERNED,
      PhabricatorAuditStatusConstants::ACCEPTED,
      PhabricatorAuditStatusConstants::AUDIT_REQUESTED,
    );
    $active = array_fuse($active);

    $viewer_status = $this->getViewerAuditStatus($commit, $viewer);

    return isset($active[$viewer_status]);
  }

  protected function isViewerFullyAccepted(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {
    return $this->isViewerAuditStatusFullyAmong(
      $commit,
      $viewer,
      array(
        PhabricatorAuditStatusConstants::ACCEPTED,
      ));
  }

  protected function isViewerFullyRejected(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {
    return $this->isViewerAuditStatusFullyAmong(
      $commit,
      $viewer,
      array(
        PhabricatorAuditStatusConstants::CONCERNED,
      ));
  }

  protected function getViewerAuditStatus(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {

    if (!$viewer->getPHID()) {
      return null;
    }

    foreach ($commit->getAudits() as $audit) {
      if ($audit->getAuditorPHID() != $viewer->getPHID()) {
        continue;
      }

      return $audit->getAuditStatus();
    }

    return null;
  }

  protected function isViewerAuditStatusFullyAmong(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer,
    array $status_list) {

    $status = $this->getViewerAuditStatus($commit, $viewer);
    if ($status === null) {
      return false;
    }

    $status_map = array_fuse($status_list);
    foreach ($commit->getAudits() as $audit) {
      if (!$commit->hasAuditAuthority($viewer, $audit)) {
        continue;
      }

      $status = $audit->getAuditStatus();
      if (isset($status_map[$status])) {
        continue;
      }

      return false;
    }

    return true;
  }

  protected function applyAuditorEffect(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer,
    $value,
    $status) {

    $actor = $this->getActor();
    $acting_phid = $this->getActingAsPHID();

    $audits = $commit->getAudits();
    $audits = mpull($audits, null, 'getAuditorPHID');

    $map = array();

    $with_authority = ($status != PhabricatorAuditStatusConstants::RESIGNED);
    if ($with_authority) {
      foreach ($audits as $audit) {
        if ($commit->hasAuditAuthority($actor, $audit, $acting_phid)) {
          $map[$audit->getAuditorPHID()] = $status;
        }
      }
    }

    // In all cases, you affect yourself.
    $map[$viewer->getPHID()] = $status;

    $this->updateAudits($commit, $map);
  }

}
