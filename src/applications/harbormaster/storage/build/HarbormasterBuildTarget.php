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
  protected $dateStarted;
  protected $dateCompleted;
  protected $buildGeneration;

  const STATUS_PENDING = 'target/pending';
  const STATUS_BUILDING = 'target/building';
  const STATUS_WAITING = 'target/waiting';
  const STATUS_PASSED = 'target/passed';
  const STATUS_FAILED = 'target/failed';
  const STATUS_ABORTED = 'target/aborted';

  private $build = self::ATTACHABLE;
  private $buildStep = self::ATTACHABLE;
  private $implementation;

  public static function getBuildTargetStatusName($status) {
    switch ($status) {
      case self::STATUS_PENDING:
        return pht('Pending');
      case self::STATUS_BUILDING:
        return pht('Building');
      case self::STATUS_WAITING:
        return pht('Waiting for Message');
      case self::STATUS_PASSED:
        return pht('Passed');
      case self::STATUS_FAILED:
        return pht('Failed');
      case self::STATUS_ABORTED:
        return pht('Aborted');
      default:
        return pht('Unknown');
    }
  }

  public static function getBuildTargetStatusIcon($status) {
    switch ($status) {
      case self::STATUS_PENDING:
        return PHUIStatusItemView::ICON_OPEN;
      case self::STATUS_BUILDING:
      case self::STATUS_WAITING:
        return PHUIStatusItemView::ICON_RIGHT;
      case self::STATUS_PASSED:
        return PHUIStatusItemView::ICON_ACCEPT;
      case self::STATUS_FAILED:
        return PHUIStatusItemView::ICON_REJECT;
      case self::STATUS_ABORTED:
        return PHUIStatusItemView::ICON_MINUS;
      default:
        return PHUIStatusItemView::ICON_QUESTION;
    }
  }

  public static function getBuildTargetStatusColor($status) {
    switch ($status) {
      case self::STATUS_PENDING:
      case self::STATUS_BUILDING:
      case self::STATUS_WAITING:
        return 'blue';
      case self::STATUS_PASSED:
        return 'green';
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
        return 'red';
      default:
        return 'bluegrey';
    }
  }

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
      ->setVariables($variables)
      ->setBuildGeneration($build->getBuildGeneration());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
        'variables' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'className' => 'text255',
        'targetStatus' => 'text64',
        'dateStarted' => 'epoch?',
        'dateCompleted' => 'epoch?',
        'buildGeneration' => 'uint32',

        // T6203/NULLABILITY
        // This should not be nullable.
        'name' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_build' => array(
          'columns' => array('buildPHID', 'buildStepPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildTargetPHIDType::TYPECONST);
  }

  public function attachBuild(HarbormasterBuild $build) {
    $this->build = $build;
    return $this;
  }

  public function getBuild() {
    return $this->assertAttached($this->build);
  }

  public function attachBuildStep(HarbormasterBuildStep $step = null) {
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

  public function isAutotarget() {
    try {
      return (bool)$this->getImplementation()->getBuildStepAutotargetPlanKey();
    } catch (Exception $e) {
      return false;
    }
  }

  public function getName() {
    if (strlen($this->name) && !$this->isAutotarget()) {
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

  public function createArtifact(
    PhabricatorUser $actor,
    $artifact_key,
    $artifact_type,
    array $artifact_data) {

    $impl = HarbormasterArtifact::getArtifactType($artifact_type);
    if (!$impl) {
      throw new Exception(
        pht(
          'There is no implementation available for artifacts of type "%s".',
          $artifact_type));
    }

    $impl->validateArtifactData($artifact_data);

    $artifact = HarbormasterBuildArtifact::initializeNewBuildArtifact($this)
      ->setArtifactKey($artifact_key)
      ->setArtifactType($artifact_type)
      ->setArtifactData($artifact_data);

    $impl = $artifact->getArtifactImplementation();
    $impl->willCreateArtifact($actor);

    return $artifact->save();
  }

  public function loadArtifact($artifact_key) {
    $indexes = array();

    $indexes[] = HarbormasterBuildArtifact::getArtifactIndex(
      $this,
      $artifact_key);

    $artifact = id(new HarbormasterBuildArtifactQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withArtifactIndexes($indexes)
      ->executeOne();
    if ($artifact === null) {
      throw new Exception(
        pht(
          'Artifact "%s" not found!',
          $artifact_key));
    }

    return $artifact;
  }

  public function newLog($log_source, $log_type) {
    $log_source = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(250)
      ->truncateString($log_source);

    $log = HarbormasterBuildLog::initializeNewBuildLog($this)
      ->setLogSource($log_source)
      ->setLogType($log_type)
      ->openBuildLog();

    return $log;
  }

  public function getFieldValue($key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      $this->getBuildStep(),
      PhabricatorCustomField::ROLE_VIEW);

    $fields = $field_list->getFields();
    $full_key = "std:harbormaster:core:{$key}";

    $field = idx($fields, $full_key);
    if (!$field) {
      throw new Exception(
        pht(
          'Unknown build step field "%s"!',
          $key));
    }

    $field = clone $field;
    $field->setValueFromStorage($this->getDetail($key));
    return $field->getBuildTargetFieldValue();
  }



/* -(  Status  )------------------------------------------------------------- */


  public function isComplete() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_PASSED:
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
        return true;
    }

    return false;
  }


  public function isFailed() {
    switch ($this->getTargetStatus()) {
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
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
