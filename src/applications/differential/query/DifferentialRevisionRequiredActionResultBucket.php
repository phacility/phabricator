<?php

final class DifferentialRevisionRequiredActionResultBucket
  extends DifferentialRevisionResultBucket {

  const BUCKETKEY = 'action';

  const KEY_MUSTREVIEW = 'must-review';
  const KEY_SHOULDREVIEW = 'should-review';

  private $objects;

  public function getResultBucketName() {
    return pht('Bucket by Required Action');
  }

  protected function buildResultGroups(
    PhabricatorSavedQuery $query,
    array $objects) {

    $this->objects = $objects;

    $phids = $query->getEvaluatedParameter('responsiblePHIDs', array());
    if (!$phids) {
      throw new Exception(
        pht(
          'You can not bucket results by required action without '.
          'specifying "Responsible Users".'));
    }
    $phids = array_fuse($phids);

    $groups = array();

    $groups[] = $this->newGroup()
      ->setName(pht('Must Review'))
      ->setKey(self::KEY_MUSTREVIEW)
      ->setNoDataString(pht('No revisions are blocked on your review.'))
      ->setObjects($this->filterMustReview($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Ready to Review'))
      ->setKey(self::KEY_SHOULDREVIEW)
      ->setNoDataString(pht('No revisions are waiting on you to review them.'))
      ->setObjects($this->filterShouldReview($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Ready to Land'))
      ->setNoDataString(pht('No revisions are ready to land.'))
      ->setObjects($this->filterShouldLand($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Ready to Update'))
      ->setNoDataString(pht('No revisions are waiting for updates.'))
      ->setObjects($this->filterShouldUpdate($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Review'))
      ->setNoDataString(pht('None of your revisions are waiting on review.'))
      ->setObjects($this->filterWaitingForReview($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Authors'))
      ->setNoDataString(pht('No revisions are waiting on author action.'))
      ->setObjects($this->filterWaitingOnAuthors($phids));

    // Because you can apply these buckets to queries which include revisions
    // that have been closed, add an "Other" bucket if we still have stuff
    // that didn't get filtered into any of the previous buckets.
    if ($this->objects) {
      $groups[] = $this->newGroup()
        ->setName(pht('Other Revisions'))
        ->setObjects($this->objects);
    }

    return $groups;
  }

  private function filterMustReview(array $phids) {
    $blocking = array(
      DifferentialReviewerStatus::STATUS_BLOCKING,
      DifferentialReviewerStatus::STATUS_REJECTED,
      DifferentialReviewerStatus::STATUS_REJECTED_OLDER,
    );
    $blocking = array_fuse($blocking);

    $objects = $this->getRevisionsUnderReview($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$this->hasReviewersWithStatus($object, $phids, $blocking)) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldReview(array $phids) {
    $reviewing = array(
      DifferentialReviewerStatus::STATUS_ADDED,
      DifferentialReviewerStatus::STATUS_COMMENTED,
    );
    $reviewing = array_fuse($reviewing);

    $objects = $this->getRevisionsUnderReview($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$this->hasReviewersWithStatus($object, $phids, $reviewing)) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldLand(array $phids) {
    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;

    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if ($object->getStatus() != $status_accepted) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldUpdate(array $phids) {
    $statuses = array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED,
      ArcanistDifferentialRevisionStatus::IN_PREPARATION,
    );
    $statuses = array_fuse($statuses);

    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (empty($statuses[$object->getStatus()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingForReview(array $phids) {
    $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;

    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if ($object->getStatus() != $status_review) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingOnAuthors(array $phids) {
    $statuses = array(
      ArcanistDifferentialRevisionStatus::ACCEPTED,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED,
      ArcanistDifferentialRevisionStatus::IN_PREPARATION,
    );
    $statuses = array_fuse($statuses);

    $objects = $this->getRevisionsNotAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (empty($statuses[$object->getStatus()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

}
