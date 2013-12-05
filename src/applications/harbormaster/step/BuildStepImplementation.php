<?php

abstract class BuildStepImplementation {

  private $settings;

  const SETTING_TYPE_STRING = 'string';
  const SETTING_TYPE_INTEGER = 'integer';
  const SETTING_TYPE_BOOLEAN = 'boolean';

  public static function getImplementations() {
    $symbols = id(new PhutilSymbolLoader())
      ->setAncestorClass("BuildStepImplementation")
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();
    return ipull($symbols, 'name');
  }

  /**
   * The name of the implementation.
   */
  abstract public function getName();

  /**
   * The generic description of the implementation.
   */
  public function getGenericDescription() {
    return '';
  }

  /**
   * The description of the implementation, based on the current settings.
   */
  public function getDescription() {
    return '';
  }

  /**
   * Run the build target against the specified build.
   */
  abstract public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target);

  /**
   * Gets the settings for this build step.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Validate the current settings of this build step.
   */
  public function validate() {
    return true;
  }

  /**
   * Loads the settings for this build step implementation from a build target.
   */
  public final function loadSettings(HarbormasterBuildTarget $build_target) {
    $this->settings = array();
    $this->validateSettingDefinitions();
    foreach ($this->getSettingDefinitions() as $name => $opt) {
      $this->settings[$name] = $build_target->getDetail($name);
    }
    return $this->settings;
  }

  /**
   * Validates that the setting definitions for this implementation are valid.
   */
  public final function validateSettingDefinitions() {
    foreach ($this->getSettingDefinitions() as $name => $opt) {
      if (!isset($opt['type'])) {
        throw new Exception(
          'Setting definition \''.$name.
          '\' is missing type requirement.');
      }
    }
  }

  /**
   * Return an array of settings for this step implementation.
   */
  public function getSettingDefinitions() {
    return array();
  }

  /**
   * Return relevant setting instructions as Remarkup.
   */
  public function getSettingRemarkupInstructions() {
    return null;
  }
}
