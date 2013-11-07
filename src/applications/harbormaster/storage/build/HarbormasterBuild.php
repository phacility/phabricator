<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;

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

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(self::STATUS_INACTIVE);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildPlan::TYPECONST);
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
