<?php

final class HarbormasterArcUnitBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  const STEPKEY = 'arcanist.unit';

  public function getBuildStepAutotargetPlanKey() {
    return HarbormasterBuildArcanistAutoplan::PLANKEY;
  }

  public function getBuildStepAutotargetStepKey() {
    return self::STEPKEY;
  }

  public function shouldRequireAutotargeting() {
    return true;
  }

  public function getName() {
    return pht('Arcanist Unit Results');
  }

  public function getGenericDescription() {
    return pht('Automatic `arc unit` step.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterBuiltinBuildStepGroup::GROUPKEY;
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    return;
  }

  public function shouldWaitForMessage(HarbormasterBuildTarget $target) {
    return true;
  }

}
