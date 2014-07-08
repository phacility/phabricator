<?php

final class HarbormasterBuildTarget extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $buildPHID;
  protected $buildStepPHID;
  protected $className;
  protected $details;
  protected $variables;
  protected $targetStatus;

  const STATUS_PENDING = 'target/pending';
  const STATUS_BUILDING = 'target/building';
  const STATUS_WAITING = 'target/waiting';
  const STATUS_PASSED = 'target/passed';
  const STATUS_FAILED = 'target/failed';

  private $build = self::ATTACHABLE;
  private $buildStep = self::ATTACHABLE;
  private $implementation;

  public static function initializeNewBuildTarget(
    HarbormasterBuild $build,
    HarbormasterBuildStep $build_step,
    array $variables) {
    return id(new HarbormasterBuildTarget())
      ->setName($build_step->getName())
      ->setBuildPHID($build->getPHID())
      ->setBuildStepPHID($build_step->getPHID())
      ->setClassName($build_step->getClassName())
      ->setDetails($build_step->getDetails())
      ->setTargetStatus(self::STATUS_PENDING)
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

  public function getVariables() {
    return parent::getVariables() + $this->getBuildTargetVariables();
  }

  public function getVariable($key, $default = null) {
    return idx($this->variables, $key, $default);
  }

  public function setVariable($key, $value) {
    $this->variables[$key] = $value;
    return $this;
  }

  public function getImplementation() {
    if ($this->implementation === null) {
      $obj = HarbormasterBuildStepImplementation::requireImplementation(
        $this->className);
      $obj->loadSettings($this);
      $this->implementation = $obj;
    }

    return $this->implementation;
  }

  public function getName() {
    if (strlen($this->name)) {
      return $this->name;
    }

    try {
      return $this->getImplementation()->getName();
    } catch (Exception $e) {
      return $this->getClassName();
    }
  }

  private function getBuildTargetVariables() {
    return array(
      'target.phid' => $this->getPHID(),
    );
  }


/* -(  Status  )------------------------------------------------------------- */


  public function isComplete() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_PASSED:
      case self::STATUS_FAILED:
        return true;
    }

    return false;
  }


  public function isFailed() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_FAILED:
        return true;
    }

    return false;
  }


  public function isWaiting() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_WAITING:
        return true;
    }

    return false;
  }

  public function isUnderway() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_PENDING:
      case self::STATUS_BUILDING:
        return true;
    }

    return false;
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
    return pht('Users must be able to see a build to view its build targets.');
  }

}
