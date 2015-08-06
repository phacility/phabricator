<?php

final class HarbormasterSleepBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Sleep');
  }

  public function getGenericDescription() {
    return pht('Sleep for a specified number of seconds.');
  }


  public function getBuildStepGroupKey() {
    return HarbormasterTestBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht(
      'Sleep for %s seconds.',
      $this->formatSettingForDescription('seconds'));
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();

    $target = time() + $settings['seconds'];

    // Use $build_update so that we only reload every 5 seconds, but
    // the sleep mechanism remains accurate.
    $build_update = 5;

    while (time() < $target) {
      sleep(1);

      if ($build_update <= 0) {
        $build->reload();
        $build_update = 5;

        if ($this->shouldAbort($build, $build_target)) {
          throw new HarbormasterBuildAbortedException();
        }
      } else {
        $build_update -= 1;
      }
    }
  }

  public function getFieldSpecifications() {
    return array(
      'seconds' => array(
        'name' => pht('Seconds'),
        'type' => 'int',
        'required' => true,
        'caption' => pht('The number of seconds to sleep for.'),
      ),
    );
  }

}
