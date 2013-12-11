<?php

abstract class BuildStepImplementation {

  private $settings;

  const SETTING_TYPE_STRING = 'string';
  const SETTING_TYPE_INTEGER = 'integer';
  const SETTING_TYPE_BOOLEAN = 'boolean';
  const SETTING_TYPE_ARTIFACT = 'artifact';

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
  public function validateSettings() {
    return true;
  }

  /**
   * Loads the settings for this build step implementation from a build
   * step or target.
   */
  public final function loadSettings($build_object) {
    $this->settings = array();
    $this->validateSettingDefinitions();
    foreach ($this->getSettingDefinitions() as $name => $opt) {
      $this->settings[$name] = $build_object->getDetail($name);
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

  /**
   * Return the name of artifacts produced by this command.
   *
   * Something like:
   *
   *   return array(
   *     'some_name_input_by_user' => 'host');
   *
   * Future steps will calculate all available artifact mappings
   * before them and filter on the type.
   *
   * @return array The mappings of artifact names to their types.
   */
  public function getArtifactMappings() {
    return array();
  }

  /**
   * Returns a list of all artifacts made available by previous build steps.
   */
  public static function loadAvailableArtifacts(
    HarbormasterBuildPlan $build_plan,
    HarbormasterBuildStep $current_build_step,
    $artifact_type) {

    $build_steps = $build_plan->loadOrderedBuildSteps();

    return self::getAvailableArtifacts(
      $build_plan,
      $build_steps,
      $current_build_step,
      $artifact_type);
  }

  /**
   * Returns a list of all artifacts made available by previous build steps.
   */
  public static function getAvailableArtifacts(
    HarbormasterBuildPlan $build_plan,
    array $build_steps,
    HarbormasterBuildStep $current_build_step,
    $artifact_type) {

    $previous_implementations = array();
    foreach ($build_steps as $build_step) {
      if ($build_step->getPHID() === $current_build_step->getPHID()) {
        break;
      }
      $previous_implementations[] = $build_step->getStepImplementation();
    }

    $artifact_arrays = mpull($previous_implementations, 'getArtifactMappings');
    $artifacts = array();
    foreach ($artifact_arrays as $array) {
      foreach ($array as $name => $type) {
        if ($type !== $artifact_type && $artifact_type !== null) {
          continue;
        }
        $artifacts[$name] = $type;
      }
    }
    return $artifacts;
  }
}
