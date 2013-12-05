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

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['seconds'] === null) {
      return false;
    }
    if (!is_int($settings['seconds'])) {
      return false;
    }
    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'seconds' => array(
        'name' => 'Seconds',
        'description' => 'The number of seconds to sleep for.',
        'type' => BuildStepImplementation::SETTING_TYPE_INTEGER));
  }

}
