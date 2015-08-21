<?php

/**
 * @task autoplan Autoplans
 */
final class HarbormasterBuildPlan extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface {

  protected $name;
  protected $planStatus;
  protected $planAutoKey;

  const STATUS_ACTIVE   = 'active';
  const STATUS_DISABLED = 'disabled';

  private $buildSteps = self::ATTACHABLE;

  public static function initializeNewBuildPlan(PhabricatorUser $actor) {
    return id(new HarbormasterBuildPlan())
      ->setName('')
      ->setPlanStatus(self::STATUS_ACTIVE)
      ->attachBuildSteps(array());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort128',
        'planStatus' => 'text32',
        'planAutoKey' => 'text32?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_status' => array(
          'columns' => array('planStatus'),
        ),
        'key_name' => array(
          'columns' => array('name'),
        ),
        'key_planautokey' => array(
          'columns' => array('planAutoKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildPlanPHIDType::TYPECONST);
  }

  public function attachBuildSteps(array $steps) {
    assert_instances_of($steps, 'HarbormasterBuildStep');
    $this->buildSteps = $steps;
    return $this;
  }

  public function getBuildSteps() {
    return $this->assertAttached($this->buildSteps);
  }

  public function isDisabled() {
    return ($this->getPlanStatus() == self::STATUS_DISABLED);
  }


/* -(  Autoplans  )---------------------------------------------------------- */


  public function isAutoplan() {
    return ($this->getPlanAutoKey() !== null);
  }


  public function getAutoplan() {
    if (!$this->isAutoplan()) {
      return null;
    }

    return HarbormasterBuildAutoplan::getAutoplan($this->getPlanAutoKey());
  }


  public function canRunManually() {
    if ($this->isAutoplan()) {
      return false;
    }

    return true;
  }


  public function getName() {
    $autoplan = $this->getAutoplan();
    if ($autoplan) {
      return $autoplan->getAutoplanName();
    }

    return parent::getName();
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildPlanEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildPlanTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        // NOTE: In practice, this policy is always limited by the "Mangage
        // Build Plans" policy.

        if ($this->isAutoplan()) {
          return PhabricatorPolicies::POLICY_NOONE;
        }

        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    $messages = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->isAutoplan()) {
          $messages[] = pht(
            'This is an autoplan (a builtin plan provided by an application) '.
            'so it can not be edited.');
        }
        break;
    }

    return $messages;
  }

}
