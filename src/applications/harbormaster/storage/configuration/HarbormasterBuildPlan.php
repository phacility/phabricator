<?php

final class HarbormasterBuildPlan extends HarbormasterDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface {

  protected $name;
  protected $planStatus;

  const STATUS_ACTIVE   = 'active';
  const STATUS_DISABLED = 'disabled';

  private $buildSteps = self::ATTACHABLE;

  public static function initializeNewBuildPlan(PhabricatorUser $actor) {
    return id(new HarbormasterBuildPlan())
      ->setPlanStatus(self::STATUS_ACTIVE);
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

  public function attachBuildSteps(array $steps) {
    assert_instances_of($steps, 'HarbormasterBuildStep');
    $this->buildSteps = $steps;
    return $this;
  }

  public function getBuildSteps() {
    return $this->assertAttached($this->buildSteps);
  }

  /**
   * Returns a standard, ordered list of build steps for this build plan.
   *
   * This method should be used to load build steps for a given build plan
   * so that the ordering is consistent.
   */
  public function loadOrderedBuildSteps() {
    return id(new HarbormasterBuildStepQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBuildPlanPHIDs(array($this->getPHID()))
      ->execute();
  }

  public function isDisabled() {
    return ($this->getPlanStatus() == self::STATUS_DISABLED);
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }
}
