<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;
  protected $cancelRequested;

  private $buildable = self::ATTACHABLE;
  private $buildPlan = self::ATTACHABLE;

  /**
   * Not currently being built.
   */
  const STATUS_INACTIVE = 'inactive';

  /**
   * Pending pick up by the Harbormaster daemon.
   */
  const STATUS_PENDING = 'pending';

  /**
   * Waiting for a resource to be allocated (not yet relevant).
   */
  const STATUS_WAITING = 'waiting';

  /**
   * Current building the buildable.
   */
  const STATUS_BUILDING = 'building';

  /**
   * The build has passed.
   */
  const STATUS_PASSED = 'passed';

  /**
   * The build has failed.
   */
  const STATUS_FAILED = 'failed';

  /**
   * The build encountered an unexpected error.
   */
  const STATUS_ERROR = 'error';

  /**
   * The build has been cancelled.
   */
  const STATUS_CANCELLED = 'cancelled';

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(self::STATUS_INACTIVE)
      ->setCancelRequested(0);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuild::TYPECONST);
  }

  public function attachBuildable(HarbormasterBuildable $buildable) {
    $this->buildable = $buildable;
    return $this;
  }

  public function getBuildable() {
    return $this->assertAttached($this->buildable);
  }

  public function getName() {
    if ($this->getBuildPlan()) {
      return $this->getBuildPlan()->getName();
    }
    return pht('Build');
  }

  public function attachBuildPlan(
    HarbormasterBuildPlan $build_plan = null) {
    $this->buildPlan = $build_plan;
    return $this;
  }

  public function getBuildPlan() {
    return $this->assertAttached($this->buildPlan);
  }

  public function createLog(
    HarbormasterBuildStep $build_step,
    $log_source,
    $log_type) {

    $log = HarbormasterBuildLog::initializeNewBuildLog($this, $build_step);
    $log->setLogSource($log_source);
    $log->setLogType($log_type);
    $log->save();
    return $log;
  }

  /**
   * Checks for and handles build cancellation.  If this method returns
   * true, the caller should stop any current operations and return control
   * as quickly as possible.
   */
  public function checkForCancellation() {
    // Here we load a copy of the current build and check whether
    // the user requested cancellation.  We can't do `reload()` here
    // in case there are changes that have not yet been saved.
    $copy = id(new HarbormasterBuild())->load($this->getID());
    if ($copy->getCancelRequested()) {
      $this->setBuildStatus(HarbormasterBuild::STATUS_CANCELLED);
      $this->setCancelRequested(0);
      $this->save();
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
    return $this->getBuildable()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildable()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Users must be able to see a buildable to view its build plans.');
  }

}
