<?php

abstract class DifferentialRevisionResultBucket
  extends PhabricatorSearchResultBucket {

  public static function getAllResultBuckets() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getResultBucketKey')
      ->execute();
  }

  protected function getRevisionsUnderReview(array $objects, array $phids) {
    $results = array();

    $objects = $this->getRevisionsNotAuthored($objects, $phids);

    $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    foreach ($objects as $key => $object) {
      if ($object->getStatus() != $status_review) {
        continue;
      }

      $results[$key] = $object;
    }

    return $results;
  }

  protected function getRevisionsAuthored(array $objects, array $phids) {
    $results = array();

    foreach ($objects as $key => $object) {
      if (isset($phids[$object->getAuthorPHID()])) {
        $results[$key] = $object;
      }
    }

    return $results;
  }

  protected function getRevisionsNotAuthored(array $objects, array $phids) {
    $results = array();

    foreach ($objects as $key => $object) {
      if (empty($phids[$object->getAuthorPHID()])) {
        $results[$key] = $object;
      }
    }

    return $results;
  }

  protected function hasReviewersWithStatus(
    DifferentialRevision $revision,
    array $phids,
    array $statuses) {

    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_phid = $reviewer->getReviewerPHID();
      if (empty($phids[$reviewer_phid])) {
        continue;
      }

      $status = $reviewer->getReviewerStatus();
      if (empty($statuses[$status])) {
        continue;
      }

      return true;
    }

    return false;
  }


}
