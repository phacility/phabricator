<?php

final class SleepBuildStepImplementation extends BuildStepImplementation {

  public function getName() {
    return pht('Sleep');
  }

  public function getDescription() {
    return pht('Sleep for a specified number of seconds.');
  }

  public function execute(HarbormasterBuild $build) {
    $settings = $this->getSettings();

    sleep($settings['seconds']);
  }

  public function getSettingDefinitions() {
    return array(
      'seconds' => array());
  }

}
