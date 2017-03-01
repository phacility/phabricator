<?php

abstract class DiffusionCommitResultBucket
  extends PhabricatorSearchResultBucket {

  public static function getAllResultBuckets() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getResultBucketKey')
      ->execute();
  }

  protected function hasAuditorsWithStatus(
    PhabricatorRepositoryCommit $commit,
    array $phids,
    array $statuses) {

    foreach ($commit->getAudits() as $audit) {
      if (!isset($phids[$audit->getAuditorPHID()])) {
        continue;
      }

      if (!isset($statuses[$audit->getAuditStatus()])) {
        continue;
      }

      return true;
    }

    return false;
  }

}
