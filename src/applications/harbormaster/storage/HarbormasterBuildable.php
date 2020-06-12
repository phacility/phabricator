<?php

final class HarbormasterBuildable
  extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    HarbormasterBuildableInterface,
    PhabricatorConduitResultInterface,
    PhabricatorDestructibleInterface {

  protected $buildablePHID;
  protected $containerPHID;
  protected $buildableStatus;
  protected $isManualBuildable;

  private $buildableObject = self::ATTACHABLE;
  private $containerObject = self::ATTACHABLE;
  private $builds = self::ATTACHABLE;

  public static function initializeNewBuildable(PhabricatorUser $actor) {
    return id(new HarbormasterBuildable())
      ->setIsManualBuildable(0)
      ->setBuildableStatus(HarbormasterBuildableStatus::STATUS_PREPARING);
  }

  public function getMonogram() {
    return 'B'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  /**
   * Returns an existing buildable for the object's PHID or creates a
   * new buildable implicitly if needed.
   */
  public static function createOrLoadExisting(
    PhabricatorUser $actor,
    $buildable_object_phid,
    $container_object_phid) {

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($actor)
      ->withBuildablePHIDs(array($buildable_object_phid))
      ->withManualBuildables(false)
      ->setLimit(1)
      ->executeOne();
    if ($buildable) {
      return $buildable;
    }
    $buildable = self::initializeNewBuildable($actor)
      ->setBuildablePHID($buildable_object_phid)
      ->setContainerPHID($container_object_phid);
    $buildable->save();
    return $buildable;
  }

  /**
   * Start builds for a given buildable.
   *
   * @param phid PHID of the object to build.
   * @param phid Container PHID for the buildable.
   * @param list<HarbormasterBuildRequest> List of builds to perform.
   * @return void
   */
  public static function applyBuildPlans(
    $phid,
    $container_phid,
    array $requests) {

    assert_instances_of($requests, 'HarbormasterBuildRequest');

    if (!$requests) {
      return;
    }

    // Skip all of this logic if the Harbormaster application
    // isn't currently installed.

    $harbormaster_app = 'PhabricatorHarbormasterApplication';
    if (!PhabricatorApplication::isClassInstalled($harbormaster_app)) {
      return;
    }

    $viewer = PhabricatorUser::getOmnipotentUser();

    $buildable = self::createOrLoadExisting(
      $viewer,
      $phid,
      $container_phid);

    $plan_phids = mpull($requests, 'getBuildPlanPHID');
    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withPHIDs($plan_phids)
      ->execute();
    $plans = mpull($plans, null, 'getPHID');

    foreach ($requests as $request) {
      $plan_phid = $request->getBuildPlanPHID();
      $plan = idx($plans, $plan_phid);

      if (!$plan) {
        throw new Exception(
          pht(
            'Failed to load build plan ("%s").',
            $plan_phid));
      }

      if ($plan->isDisabled()) {
        // TODO: This should be communicated more clearly -- maybe we should
        // create the build but set the status to "disabled" or "derelict".
        continue;
      }

      $parameters = $request->getBuildParameters();
      $buildable->applyPlan($plan, $parameters, $request->getInitiatorPHID());
    }
  }

  public function applyPlan(
    HarbormasterBuildPlan $plan,
    array $parameters,
    $initiator_phid) {

    $viewer = PhabricatorUser::getOmnipotentUser();
    $build = HarbormasterBuild::initializeNewBuild($viewer)
      ->setBuildablePHID($this->getPHID())
      ->setBuildPlanPHID($plan->getPHID())
      ->setBuildParameters($parameters)
      ->setBuildStatus(HarbormasterBuildStatus::STATUS_PENDING);
    if ($initiator_phid) {
      $build->setInitiatorPHID($initiator_phid);
    }

    $auto_key = $plan->getPlanAutoKey();
    if ($auto_key) {
      $build->setPlanAutoKey($auto_key);
    }

    $build->save();

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withBuildPlanPHIDs(array($plan->getPHID()))
      ->execute();

    foreach ($steps as $step) {
      $step->willStartBuild($viewer, $this, $build, $plan);
    }

    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $build->getID(),
      ),
      array(
        'objectPHID' => $build->getPHID(),
      ));

    return $build;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'containerPHID' => 'phid?',
        'buildableStatus' => 'text32',
        'isManualBuildable' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_buildable' => array(
          'columns' => array('buildablePHID'),
        ),
        'key_container' => array(
          'columns' => array('containerPHID'),
        ),
        'key_manual' => array(
          'columns' => array('isManualBuildable'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildablePHIDType::TYPECONST);
  }

  public function attachBuildableObject($buildable_object) {
    $this->buildableObject = $buildable_object;
    return $this;
  }

  public function getBuildableObject() {
    return $this->assertAttached($this->buildableObject);
  }

  public function attachContainerObject($container_object) {
    $this->containerObject = $container_object;
    return $this;
  }

  public function getContainerObject() {
    return $this->assertAttached($this->containerObject);
  }

  public function attachBuilds(array $builds) {
    assert_instances_of($builds, 'HarbormasterBuild');
    $this->builds = $builds;
    return $this;
  }

  public function getBuilds() {
    return $this->assertAttached($this->builds);
  }


/* -(  Status  )------------------------------------------------------------- */


  public function getBuildableStatusObject() {
    $status = $this->getBuildableStatus();
    return HarbormasterBuildableStatus::newBuildableStatusObject($status);
  }

  public function getStatusIcon() {
    return $this->getBuildableStatusObject()->getIcon();
  }

  public function getStatusDisplayName() {
    return $this->getBuildableStatusObject()->getDisplayName();
  }

  public function getStatusColor() {
    return $this->getBuildableStatusObject()->getColor();
  }

  public function isPreparing() {
    return $this->getBuildableStatusObject()->isPreparing();
  }

  public function isBuilding() {
    return $this->getBuildableStatusObject()->isBuilding();
  }


/* -(  Messages  )----------------------------------------------------------- */


  public function sendMessage(
    PhabricatorUser $viewer,
    $message_type,
    $queue_update) {

    $message = HarbormasterBuildMessage::initializeNewMessage($viewer)
      ->setReceiverPHID($this->getPHID())
      ->setType($message_type)
      ->save();

    if ($queue_update) {
      PhabricatorWorker::scheduleTask(
        'HarbormasterBuildWorker',
        array(
          'buildablePHID' => $this->getPHID(),
        ),
        array(
          'objectPHID' => $this->getPHID(),
        ));
    }

    return $message;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildableTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildableTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildableObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildableObject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('A buildable inherits policies from the underlying object.');
  }



/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildableDisplayPHID() {
    return $this->getBuildableObject()->getHarbormasterBuildableDisplayPHID();
  }

  public function getHarbormasterBuildablePHID() {
    // NOTE: This is essentially just for convenience, as it allows you create
    // a copy of a buildable by specifying `B123` without bothering to go
    // look up the underlying object.
    return $this->getBuildablePHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getContainerPHID();
  }

  public function getBuildVariables() {
    return array();
  }

  public function getAvailableBuildVariables() {
    return array();
  }

  public function newBuildableEngine() {
    return $this->getBuildableObject()->newBuildableEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('objectPHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the object that is built.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('containerPHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the object containing this buildable.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildableStatus')
        ->setType('map<string, wild>')
        ->setDescription(pht('The current status of this buildable.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isManual')
        ->setType('bool')
        ->setDescription(pht('True if this is a manual buildable.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('uri')
        ->setType('uri')
        ->setDescription(pht('View URI for the buildable.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'objectPHID' => $this->getBuildablePHID(),
      'containerPHID' => $this->getContainerPHID(),
      'buildableStatus' => array(
        'value' => $this->getBuildableStatus(),
      ),
      'isManual' => (bool)$this->getIsManualBuildable(),
      'uri' => PhabricatorEnv::getURI($this->getURI()),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $viewer = $engine->getViewer();

    $this->openTransaction();
      $builds = id(new HarbormasterBuildQuery())
        ->setViewer($viewer)
        ->withBuildablePHIDs(array($this->getPHID()))
        ->execute();
      foreach ($builds as $build) {
        $engine->destroyObject($build);
      }

      $messages = id(new HarbormasterBuildMessageQuery())
        ->setViewer($viewer)
        ->withReceiverPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($messages as $message) {
        $engine->destroyObject($message);
      }

      $this->delete();
    $this->saveTransaction();
  }

}
