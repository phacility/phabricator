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

    $phids = $query->getEvaluatedParameter('responsiblePHIDs');
    if (!$phids) {
      throw new Exception(
        pht(
          'You can not bucket results by required action without '.
          'specifying "Responsible Users".'));
    }
    $phids = array_fuse($phids);

    // Before continuing, throw away any revisions which responsible users
    // have explicitly resigned from.

    // The goal is to allow users to resign from revisions they don't want to
    // review to get these revisions off their dashboard, even if there are
    // other project or package reviewers which they have authority over.
    $this->filterResigned($phids);

    // We also throw away draft revisions which you aren't the author of.
    $this->filterOtherDrafts($phids);

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
      ->setName(pht('Drafts'))
      ->setNoDataString(pht('You have no draft revisions.'))
      ->setObjects($this->filterDrafts($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Review'))
      ->setNoDataString(pht('None of your revisions are waiting on review.'))
      ->setObjects($this->filterWaitingForReview($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Authors'))
      ->setNoDataString(pht('No revisions are waiting on author action.'))
      ->setObjects($this->filterWaitingOnAuthors($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Other Reviewers'))
      ->setNoDataString(pht('No revisions are waiting for other reviewers.'))
      ->setObjects($this->filterWaitingOnOtherReviewers($phids));

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

      // If an author has used "Request Review" to put an accepted revision
      // back into the "Needs Review" state, include "Accepted" reviewers
      // whose reviews have been voided in the "Should Review" bucket.

      // If we don't do this, they end up in "Waiting on Other Reviewers",
      // even if there are no other reviewers.
      DifferentialReviewerStatus::STATUS_ACCEPTED,
    );
    $reviewing = array_fuse($reviewing);

    $objects = $this->getRevisionsUnderReview($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$this->hasReviewersWithStatus($object, $phids, $reviewing, true)) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldLand(array $phids) {
    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$object->isAccepted()) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldUpdate(array $phids) {
    $statuses = array(
      DifferentialRevisionStatus::NEEDS_REVISION,
      DifferentialRevisionStatus::CHANGES_PLANNED,
    );
    $statuses = array_fuse($statuses);

    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (empty($statuses[$object->getModernRevisionStatus()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingForReview(array $phids) {
    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$object->isNeedsReview()) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingOnAuthors(array $phids) {
    $statuses = array(
      DifferentialRevisionStatus::ACCEPTED,
      DifferentialRevisionStatus::NEEDS_REVISION,
      DifferentialRevisionStatus::CHANGES_PLANNED,
    );
    $statuses = array_fuse($statuses);

    $objects = $this->getRevisionsNotAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (empty($statuses[$object->getModernRevisionStatus()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingOnOtherReviewers(array $phids) {
    $objects = $this->getRevisionsNotAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$object->isNeedsReview()) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterResigned(array $phids) {
    $resigned = array(
      DifferentialReviewerStatus::STATUS_RESIGNED,
    );
    $resigned = array_fuse($resigned);

    $objects = $this->getRevisionsNotAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$this->hasReviewersWithStatus($object, $phids, $resigned)) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterOtherDrafts(array $phids) {
    $objects = $this->getRevisionsNotAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$object->isDraft()) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterDrafts(array $phids) {
    $objects = $this->getRevisionsAuthored($this->objects, $phids);

    $results = array();
    foreach ($objects as $key => $object) {
      if (!$object->isDraft()) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

}
