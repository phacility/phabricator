<?php

final class HarbormasterArcLintBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  const STEPKEY = 'arcanist.lint';

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
    return pht('Arcanist Lint Results');
  }

  public function getGenericDescription() {
    return pht('Automatic `arc lint` step.');
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
