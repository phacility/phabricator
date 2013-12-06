<?php

final class HarbormasterBuildStep extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildPlanPHID;
  protected $className;
  protected $details = array();
  protected $sequence;

  private $buildPlan = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      )
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildStep::TYPECONST);
  }

  public function attachBuildPlan(HarbormasterBuildPlan $plan) {
    $this->buildPlan = $plan;
    return $this;
  }

  public function getBuildPlan() {
    return $this->assertAttached($this->buildPlan);
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function getStepImplementation() {
    if ($this->className === null) {
      throw new Exception("No implementation set for the given step.");
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
    return $this->getBuildPlan()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildPlan()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('A build step has the same policies as its build plan.');
  }
}
