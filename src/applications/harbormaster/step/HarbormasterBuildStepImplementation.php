<?php

abstract class HarbormasterBuildStepImplementation {

  public static function getImplementations() {
    return id(new PhutilSymbolLoader())
      ->setAncestorClass('HarbormasterBuildStepImplementation')
      ->loadObjects();
  }

  public static function getImplementation($class) {
    $base = idx(self::getImplementations(), $class);

    if ($base) {
      return (clone $base);
    }

    return null;
  }

  public static function requireImplementation($class) {
    if (!$class) {
      throw new Exception(pht('No implementation is specified!'));
    }

    $implementation = self::getImplementation($class);
    if (!$implementation) {
      throw new Exception(pht('No such implementation "%s" exists!', $class));
    }

    return $implementation;
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
    return $this->getGenericDescription();
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

  public function getSetting($key, $default = null) {
    return idx($this->settings, $key, $default);
  }

  /**
   * Loads the settings for this build step implementation from a build
   * step or target.
   */
  public final function loadSettings($build_object) {
    $this->settings = $build_object->getDetails();
    return $this;
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
  public function getArtifactInputs() {
    return array();
  }

  public function getArtifactOutputs() {
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

    $artifact_arrays = mpull($previous_implementations, 'getArtifactOutputs');
    $artifacts = array();
    foreach ($artifact_arrays as $array) {
      $array = ipull($array, 'type', 'key');
      foreach ($array as $name => $type) {
        if ($type !== $artifact_type && $artifact_type !== null) {
          continue;
        }
        $artifacts[$name] = $type;
      }
    }
    return $artifacts;
  }

  /**
   * Convert a user-provided string with variables in it, like:
   *
   *   ls ${dirname}
   *
   * ...into a string with variables merged into it safely:
   *
   *   ls 'dir with spaces'
   *
   * @param string Name of a `vxsprintf` function, like @{function:vcsprintf}.
   * @param string User-provided pattern string containing `${variables}`.
   * @param dict   List of available replacement variables.
   * @return string String with variables replaced safely into it.
   */
  protected function mergeVariables($function, $pattern, array $variables) {
    $regexp = '/\\$\\{(?P<name>[a-z\\.]+)\\}/';

    $matches = null;
    preg_match_all($regexp, $pattern, $matches);

    $argv = array();
    foreach ($matches['name'] as $name) {
      if (!array_key_exists($name, $variables)) {
        throw new Exception(pht("No such variable '%s'!", $name));
      }
      $argv[] = $variables[$name];
    }

    $pattern = str_replace('%', '%%', $pattern);
    $pattern = preg_replace($regexp, '%s', $pattern);

    return call_user_func($function, $pattern, $argv);
  }

  public function getFieldSpecifications() {
    return array();
  }

  protected function formatSettingForDescription($key, $default = null) {
    return $this->formatValueForDescription($this->getSetting($key, $default));
  }

  protected function formatValueForDescription($value) {
    if (strlen($value)) {
      return phutil_tag('strong', array(), $value);
    } else {
      return phutil_tag('em', array(), pht('(null)'));
    }
  }

}
