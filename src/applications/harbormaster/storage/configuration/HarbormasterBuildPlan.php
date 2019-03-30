<?php

/**
 * @task autoplan Autoplans
 */
final class HarbormasterBuildPlan extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorNgramsInterface,
    PhabricatorConduitResultInterface,
    PhabricatorProjectInterface,
    PhabricatorPolicyCodexInterface {

  protected $name;
  protected $planStatus;
  protected $planAutoKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $properties = array();

  const STATUS_ACTIVE   = 'active';
  const STATUS_DISABLED = 'disabled';

  private $buildSteps = self::ATTACHABLE;

  public static function initializeNewBuildPlan(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorHarbormasterApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      HarbormasterBuildPlanDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      HarbormasterBuildPlanDefaultEditCapability::CAPABILITY);

    return id(new HarbormasterBuildPlan())
      ->setName('')
      ->setPlanStatus(self::STATUS_ACTIVE)
      ->attachBuildSteps(array())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
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

  public function getURI() {
    return urisprintf(
      '/harbormaster/plan/%s/',
      $this->getID());
  }

  public function getObjectName() {
    return pht('Plan %d', $this->getID());
  }

  public function getPlanProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setPlanProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
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

  public function hasRunCapability(PhabricatorUser $viewer) {
    try {
      $this->assertHasRunCapability($viewer);
      return true;
    } catch (PhabricatorPolicyException $ex) {
      return false;
    }
  }

  public function canRunWithoutEditCapability() {
    $runnable = HarbormasterBuildPlanBehavior::BEHAVIOR_RUNNABLE;
    $if_viewable = HarbormasterBuildPlanBehavior::RUNNABLE_IF_VIEWABLE;

    $option = HarbormasterBuildPlanBehavior::getBehavior($runnable)
      ->getPlanOption($this);

    return ($option->getKey() === $if_viewable);
  }

  public function assertHasRunCapability(PhabricatorUser $viewer) {
    if ($this->canRunWithoutEditCapability()) {
      $capability = PhabricatorPolicyCapability::CAN_VIEW;
    } else {
      $capability = PhabricatorPolicyCapability::CAN_EDIT;
    }

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $this,
      $capability);
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildPlanEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildPlanTransaction();
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
        if ($this->isAutoplan()) {
          return PhabricatorPolicies::getMostOpenPolicy();
        }
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->isAutoplan()) {
          return PhabricatorPolicies::POLICY_NOONE;
        }
        return $this->getEditPolicy();
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


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new HarbormasterBuildPlanNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of this build plan.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('map<string, wild>')
        ->setDescription(pht('The current status of this build plan.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('behaviors')
        ->setType('map<string, string>')
        ->setDescription(pht('Behavior configuration for the build plan.')),
    );
  }

  public function getFieldValuesForConduit() {
    $behavior_map = array();

    $behaviors = HarbormasterBuildPlanBehavior::newPlanBehaviors();
    foreach ($behaviors as $behavior) {
      $option = $behavior->getPlanOption($this);

      $behavior_map[$behavior->getKey()] = array(
        'value' => $option->getKey(),
      );
    }

    return array(
      'name' => $this->getName(),
      'status' => array(
        'value' => $this->getPlanStatus(),
      ),
      'behaviors' => $behavior_map,
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


/* -(  PhabricatorPolicyCodexInterface  )------------------------------------ */


  public function newPolicyCodex() {
    return new HarbormasterBuildPlanPolicyCodex();
  }

}
