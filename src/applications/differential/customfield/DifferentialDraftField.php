<?php

final class DifferentialDraftField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:draft';
  }

  public function getFieldName() {
    return pht('Draft');
  }

  public function getFieldDescription() {
    return pht('Show a warning about draft revisions.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return null;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function getWarningsForRevisionHeader(array $handles) {
    $viewer = $this->getViewer();
    $revision = $this->getObject();

    if (!$revision->isDraft()) {
      return array();
    }

    // If the author has held this revision as a draft explicitly, don't
    // show any misleading messages about it autosubmitting later. We do show
    // reminder text.
    if ($revision->getHoldAsDraft()) {
      return array(
        pht(
          'This is a draft revision that has not yet been submitted for '.
          'review.'),
      );
    }

    $warnings = array();

    $blocking_map = array(
      HarbormasterBuildStatus::STATUS_FAILED,
      HarbormasterBuildStatus::STATUS_ABORTED,
      HarbormasterBuildStatus::STATUS_ERROR,
      HarbormasterBuildStatus::STATUS_PAUSED,
      HarbormasterBuildStatus::STATUS_DEADLOCKED,
    );
    $blocking_map = array_fuse($blocking_map);

    $builds = $revision->loadImpactfulBuilds($viewer);

    $waiting = array();
    $blocking = array();
    foreach ($builds as $build) {
      if (isset($blocking_map[$build->getBuildStatus()])) {
        $blocking[] = $build;
      } else {
        $waiting[] = $build;
      }
    }

    $blocking_list = $viewer->renderHandleList(mpull($blocking, 'getPHID'))
      ->setAsInline(true);
    $waiting_list = $viewer->renderHandleList(mpull($waiting, 'getPHID'))
      ->setAsInline(true);

    if ($blocking) {
      $warnings[] = pht(
        'This draft revision will not be submitted for review because %s '.
        'build(s) failed: %s.',
        phutil_count($blocking),
        $blocking_list);
      $warnings[] = pht(
        'Fix build failures and update the revision.');
    } else if ($waiting) {
      $warnings[] = pht(
        'This draft revision will be sent for review once %s '.
        'build(s) pass: %s.',
        phutil_count($waiting),
        $waiting_list);
    } else {
      $warnings[] = pht(
        'This is a draft revision that has not yet been submitted for '.
        'review.');
    }

    return $warnings;
  }

  public function getWarningsForDetailView() {
    $revision = $this->getObject();

    if ($revision->getShouldBroadcast()) {
      return array();
    }

    return array(
      pht(
        'This revision is currently a draft. You can leave comments, but '.
        'no one will be notified until the revision is submitted for '.
        'review.'),
    );
  }

}
