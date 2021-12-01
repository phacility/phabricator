<?php

final class HarbormasterAbortOlderBuildsBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Abort Older Builds');
  }

  public function getGenericDescription() {
    return pht(
      'When building a revision, abort copies of this build plan which are '.
      'currently running against older diffs.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterControlBuildStepGroup::GROUPKEY;
  }

  public function getEditInstructions() {
    return pht(<<<EOTEXT
When run against a revision, this build step will abort any older copies of
the same build plan which are currently running against older diffs.

There are some nuances to the behavior:

  - if this build step is triggered manually, it won't abort anything;
  - this build step won't abort manual builds;
  - this build step won't abort anything if the diff it is building isn't
    the active diff when it runs.

Build results on outdated diffs often aren't very important, so this may
reduce build queue load without any substantial cost.
EOTEXT
      );
  }

  public function willStartBuild(
    PhabricatorUser $viewer,
    HarbormasterBuildable $buildable,
    HarbormasterBuild $build,
    HarbormasterBuildPlan $plan,
    HarbormasterBuildStep $step) {

    if ($buildable->getIsManualBuildable()) {
      // Don't abort anything if this is a manual buildable.
      return;
    }

    $object_phid = $buildable->getBuildablePHID();
    if (phid_get_type($object_phid) !== DifferentialDiffPHIDType::TYPECONST) {
      // If this buildable isn't building a diff, bail out. For example, we
      // might be building a commit. In this case, this step has no effect.
      return;
    }

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$diff) {
      return;
    }

    $revision_id = $diff->getRevisionID();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision_id))
      ->executeOne();
    if (!$revision) {
      return;
    }

    $active_phid = $revision->getActiveDiffPHID();
    if ($active_phid !== $object_phid) {
      // If we aren't building the active diff, bail out.
      return;
    }

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withRevisionIDs(array($revision_id))
      ->execute();
    $abort_diff_phids = array();
    foreach ($diffs as $diff) {
      if ($diff->getPHID() !== $active_phid) {
        $abort_diff_phids[] = $diff->getPHID();
      }
    }

    if (!$abort_diff_phids) {
      return;
    }

    // We're fetching buildables even if they have "passed" or "failed"
    // because they may still have ongoing builds. At the time of writing
    // only "failed" buildables may still be ongoing, but it seems likely that
    // "passed" buildables may be ongoing in the future.

    $abort_buildables = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs($abort_diff_phids)
      ->withManualBuildables(false)
      ->execute();
    if (!$abort_buildables) {
      return;
    }

    $statuses = HarbormasterBuildStatus::getIncompleteStatusConstants();

    $abort_builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(mpull($abort_buildables, 'getPHID'))
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->withBuildStatuses($statuses)
      ->execute();
    if (!$abort_builds) {
      return;
    }

    foreach ($abort_builds as $abort_build) {
      $abort_build->sendMessage(
        $viewer,
        HarbormasterBuildMessageAbortTransaction::MESSAGETYPE);
    }
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    return;
  }

}
