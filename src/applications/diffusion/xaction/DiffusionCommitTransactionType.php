<?php

abstract class DiffusionCommitTransactionType
  extends PhabricatorModularTransactionType {

  protected function updateAudits(
    PhabricatorRepositoryCommit $commit,
    array $new) {

    $audits = $commit->getAudits();
    $audits = mpull($audits, null, 'getAuditorPHID');

    foreach ($new as $phid => $status) {
      $audit = idx($audits, $phid);
      if (!$audit) {
        $audit = id(new PhabricatorRepositoryAuditRequest())
          ->setAuditorPHID($phid)
          ->setCommitPHID($commit->getPHID());

        $audits[$phid] = $audit;
      } else {
        if ($audit->getAuditStatus() === $status) {
          continue;
        }
      }

      $audit
        ->setAuditStatus($status)
        ->save();
    }

    $commit->attachAudits($audits);

    return $audits;
  }

}
