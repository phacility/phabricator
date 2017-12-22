<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorConduitResultInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;
  protected $buildGeneration;
  protected $buildParameters = array();
  protected $initiatorPHID;
  protected $planAutoKey;

  private $buildable = self::ATTACHABLE;
  private $buildPlan = self::ATTACHABLE;
  private $buildTargets = self::ATTACHABLE;
  private $unprocessedCommands = self::ATTACHABLE;

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(HarbormasterBuildStatus::STATUS_INACTIVE)
      ->setBuildGeneration(0);
  }

  public function delete() {
    $this->openTransaction();
      $this->deleteUnprocessedCommands();
      $result = parent::delete();
    $this->saveTransaction();

    return $result;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'buildParameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'buildStatus' => 'text32',
        'buildGeneration' => 'uint32',
        'planAutoKey' => 'text32?',
        'initiatorPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_buildable' => array(
          'columns' => array('buildablePHID'),
        ),
        'key_plan' => array(
          'columns' => array('buildPlanPHID'),
        ),
        'key_status' => array(
          'columns' => array('buildStatus'),
        ),
        'key_planautokey' => array(
          'columns' => array('buildablePHID', 'planAutoKey'),
          'unique' => true,
        ),
        'key_initiator' => array(
          'columns' => array('initiatorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildPHIDType::TYPECONST);
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

  public function getBuildTargets() {
    return $this->assertAttached($this->buildTargets);
  }

  public function attachBuildTargets(array $targets) {
    $this->buildTargets = $targets;
    return $this;
  }

  public function isBuilding() {
    return
      $this->getBuildStatus() === HarbormasterBuildStatus::STATUS_PENDING ||
      $this->getBuildStatus() === HarbormasterBuildStatus::STATUS_BUILDING;
  }

  public function isAutobuild() {
    return ($this->getPlanAutoKey() !== null);
  }

  public function retrieveVariablesFromBuild() {
    $results = array(
      'buildable.diff' => null,
      'buildable.revision' => null,
      'buildable.commit' => null,
      'repository.callsign' => null,
      'repository.phid' => null,
      'repository.vcs' => null,
      'repository.uri' => null,
      'step.timestamp' => null,
      'build.id' => null,
      'initiator.phid' => null,
    );

    foreach ($this->getBuildParameters() as $key => $value) {
      $results['build/'.$key] = $value;
    }

    $buildable = $this->getBuildable();
    $object = $buildable->getBuildableObject();

    $object_variables = $object->getBuildVariables();

    $results = $object_variables + $results;

    $results['step.timestamp'] = time();
    $results['build.id'] = $this->getID();
    $results['initiator.phid'] = $this->getInitiatorPHID();

    return $results;
  }

  public static function getAvailableBuildVariables() {
    $objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('HarbormasterBuildableInterface')
      ->execute();

    $variables = array();
    $variables[] = array(
      'step.timestamp' => pht('The current UNIX timestamp.'),
      'build.id' => pht('The ID of the current build.'),
      'target.phid' => pht('The PHID of the current build target.'),
      'initiator.phid' => pht(
        'The PHID of the user or Object that initiated the build, '.
        'if applicable.'),
    );

    foreach ($objects as $object) {
      $variables[] = $object->getAvailableBuildVariables();
    }

    $variables = array_mergev($variables);
    return $variables;
  }

  public function isComplete() {
    return in_array(
      $this->getBuildStatus(),
      HarbormasterBuildStatus::getCompletedStatusConstants());
  }

  public function isPaused() {
    return ($this->getBuildStatus() == HarbormasterBuildStatus::STATUS_PAUSED);
  }

  public function getURI() {
    $id = $this->getID();
    return "/harbormaster/build/{$id}/";
  }


/* -(  Build Commands  )----------------------------------------------------- */


  private function getUnprocessedCommands() {
    return $this->assertAttached($this->unprocessedCommands);
  }

  public function attachUnprocessedCommands(array $commands) {
    $this->unprocessedCommands = $commands;
    return $this;
  }

  public function canRestartBuild() {
    if ($this->isAutobuild()) {
      return false;
    }

    return !$this->isRestarting();
  }

  public function canPauseBuild() {
    if ($this->isAutobuild()) {
      return false;
    }

    return !$this->isComplete() &&
           !$this->isPaused() &&
           !$this->isPausing();
  }

  public function canAbortBuild() {
    if ($this->isAutobuild()) {
      return false;
    }

    return !$this->isComplete();
  }

  public function canResumeBuild() {
    if ($this->isAutobuild()) {
      return false;
    }

    return $this->isPaused() &&
           !$this->isResuming();
  }

  public function isPausing() {
    $is_pausing = false;
    foreach ($this->getUnprocessedCommands() as $command_object) {
      $command = $command_object->getCommand();
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_PAUSE:
          $is_pausing = true;
          break;
        case HarbormasterBuildCommand::COMMAND_RESUME:
        case HarbormasterBuildCommand::COMMAND_RESTART:
          $is_pausing = false;
          break;
        case HarbormasterBuildCommand::COMMAND_ABORT:
          $is_pausing = true;
          break;
      }
    }

    return $is_pausing;
  }

  public function isResuming() {
    $is_resuming = false;
    foreach ($this->getUnprocessedCommands() as $command_object) {
      $command = $command_object->getCommand();
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_RESTART:
        case HarbormasterBuildCommand::COMMAND_RESUME:
          $is_resuming = true;
          break;
        case HarbormasterBuildCommand::COMMAND_PAUSE:
          $is_resuming = false;
          break;
        case HarbormasterBuildCommand::COMMAND_ABORT:
          $is_resuming = false;
          break;
      }
    }

    return $is_resuming;
  }

  public function isRestarting() {
    $is_restarting = false;
    foreach ($this->getUnprocessedCommands() as $command_object) {
      $command = $command_object->getCommand();
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_RESTART:
          $is_restarting = true;
          break;
      }
    }

    return $is_restarting;
  }

  public function isAborting() {
    $is_aborting = false;
    foreach ($this->getUnprocessedCommands() as $command_object) {
      $command = $command_object->getCommand();
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_ABORT:
          $is_aborting = true;
          break;
      }
    }

    return $is_aborting;
  }

  public function deleteUnprocessedCommands() {
    foreach ($this->getUnprocessedCommands() as $key => $command_object) {
      $command_object->delete();
      unset($this->unprocessedCommands[$key]);
    }

    return $this;
  }

  public function canIssueCommand(PhabricatorUser $viewer, $command) {
    try {
      $this->assertCanIssueCommand($viewer, $command);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }

  public function assertCanIssueCommand(PhabricatorUser $viewer, $command) {
    $need_edit = false;
    switch ($command) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
      case HarbormasterBuildCommand::COMMAND_RESUME:
      case HarbormasterBuildCommand::COMMAND_ABORT:
        $need_edit = true;
        break;
      default:
        throw new Exception(
          pht(
            'Invalid Harbormaster build command "%s".',
            $command));
    }

    // Issuing these commands requires that you be able to edit the build, to
    // prevent enemy engineers from sabotaging your builds. See T9614.
    if ($need_edit) {
      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $this->getBuildPlan(),
        PhabricatorPolicyCapability::CAN_EDIT);
    }
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildTransaction();
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
    return $this->getBuildable()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildable()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('A build inherits policies from its buildable.');
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildablePHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the object this build is building.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildPlanPHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the build plan being run.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildStatus')
        ->setType('map<string, wild>')
        ->setDescription(pht('The current status of this build.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('initiatorPHID')
        ->setType('phid')
        ->setDescription(pht('The person (or thing) that started this build.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of this build.')),
    );
  }

  public function getFieldValuesForConduit() {
    $status = $this->getBuildStatus();
    return array(
      'buildablePHID' => $this->getBuildablePHID(),
      'buildPlanPHID' => $this->getBuildPlanPHID(),
      'buildStatus' => array(
        'value' => $status,
        'name' => HarbormasterBuildStatus::getBuildStatusName($status),
        'color.ansi' =>
          HarbormasterBuildStatus::getBuildStatusANSIColor($status),
      ),
      'initiatorPHID' => nonempty($this->getInitiatorPHID(), null),
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new HarbormasterQueryBuildsSearchEngineAttachment())
        ->setAttachmentKey('querybuilds'),
    );
  }

}
