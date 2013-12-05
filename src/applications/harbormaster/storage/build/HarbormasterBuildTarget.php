<?php

final class HarbormasterBuildTarget extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildPHID;
  protected $buildStepPHID;
  protected $className;
  protected $details;
  protected $variables;

  private $build = self::ATTACHABLE;
  private $buildStep = self::ATTACHABLE;

  public static function initializeNewBuildTarget(
    HarbormasterBuild $build,
    HarbormasterBuildStep $build_step,
    array $variables) {
    return id(new HarbormasterBuildTarget())
      ->setBuildPHID($build->getPHID())
      ->setBuildStepPHID($build_step->getPHID())
      ->setClassName($build_step->getClassName())
      ->setDetails($build_step->getDetails())
      ->setVariables($variables);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
        'variables' => self::SERIALIZATION_JSON,
      )
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildTarget::TYPECONST);
  }

  public function attachBuild(HarbormasterBuild $build) {
    $this->build = $build;
    return $this;
  }

  public function getBuild() {
    return $this->assertAttached($this->build);
  }

  public function attachBuildStep(HarbormasterBuildStep $step) {
    $this->buildStep = $step;
    return $this;
  }

  public function getBuildStep() {
    return $this->assertAttached($this->buildStep);
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function getVariable($key, $default = null) {
    return idx($this->variables, $key, $default);
  }

  public function setVariable($key, $value) {
    $this->variables[$key] = $value;
    return $this;
  }

  public function getImplementation() {
    if ($this->className === null) {
      throw new Exception("No implementation set for the given target.");
    }

    static $implementations = null;
    if ($implementations === null) {
      $implementations = BuildStepImplementation::getImplementations();
    }

    $class = $this->className;
    if (!in_array($class, $implementations)) {
      throw new Exception(
        "Class name '".$class."' does not extend BuildStepImplementation.");
    }
    $implementation = newv($class, array());
    $implementation->loadSettings($this);
    return $implementation;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuild()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuild()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Users must be able to see a build to view its build targets.');
  }

}
