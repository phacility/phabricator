<?php

final class SleepBuildStepImplementation extends BuildStepImplementation {

  public function getName() {
    return pht('Sleep');
  }

  public function getGenericDescription() {
    return pht('Sleep for a specified number of seconds.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    return pht('Sleep for %s seconds.', $settings['seconds']);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();

    sleep($settings['seconds']);
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
