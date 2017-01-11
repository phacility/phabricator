<?php

abstract class DiffusionCommitAuditTransaction
  extends DiffusionCommitActionTransaction {

  protected function getCommitActionGroupKey() {
    return DiffusionCommitEditEngine::ACTIONGROUP_AUDIT;
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

    return $this->isViewerAuditStatusAmong(
      $commit,
      $viewer,
      array(
        PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
        PhabricatorAuditStatusConstants::CONCERNED,
        PhabricatorAuditStatusConstants::ACCEPTED,
        PhabricatorAuditStatusConstants::AUDIT_REQUESTED,
      ));
  }

  protected function isViewerAcceptingAuditor(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {
    return $this->isViewerAuditStatusAmong(
      $commit,
      $viewer,
      array(
        PhabricatorAuditStatusConstants::ACCEPTED,
      ));
  }

  protected function isViewerRejectingAuditor(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer) {
    return $this->isViewerAuditStatusAmong(
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

  protected function isViewerAuditStatusAmong(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer,
    array $status_list) {

    $status = $this->getViewerAuditStatus($commit, $viewer);
    if ($status === null) {
      return false;
    }

    $status_map = array_fuse($status_list);
    return isset($status_map[$status]);
  }

  protected function applyAuditorEffect(
    PhabricatorRepositoryCommit $commit,
    PhabricatorUser $viewer,
    $value,
    $status) {

    $audits = $commit->getAudits();
    $audits = mpull($audits, null, 'getAuditorPHID');

    $map = array();

    $with_authority = ($status != PhabricatorAuditStatusConstants::RESIGNED);
    if ($with_authority) {
      $has_authority = PhabricatorAuditCommentEditor::loadAuditPHIDsForUser(
        $viewer);
      $has_authority = array_fuse($has_authority);
      foreach ($audits as $audit) {
        $auditor_phid = $audit->getAuditorPHID();
        if (isset($has_authority[$auditor_phid])) {
          $map[$auditor_phid] = $status;
        }
      }
    }

    // In all cases, you affect yourself.
    $map[$viewer->getPHID()] = $status;

    $this->updateAudits($commit, $map);
  }

}
