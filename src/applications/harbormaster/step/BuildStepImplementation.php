<?php

abstract class BuildStepImplementation {

  private $settings;

  /**
   * The name of the implementation.
   */
  abstract public function getName();

  /**
   * The description of the implementation.
   */
  public function getDescription() {
    return '';
  }

  /**
   * Run the build step against the specified build.
   */
  abstract public function execute(HarbormasterBuild $build);

  /**
   * Gets the settings for this build step.
   */
  protected function getSettings() {
    return $this->settings;
  }

  /**
   * Loads the settings for this build step implementation from the build step.
   */
  public final function loadSettings(HarbormasterBuildStep $build_step) {
    $this->settings = array();
    foreach ($this->getSettingDefinitions() as $name => $opt) {
      $this->settings[$name] = $build_step->getDetail($name);
    }
    return $this->settings;
  }

  /**
   * Return an array of settings for this step implementation.
   */
  public function getSettingDefinitions() {
    return array();
  }
}
