<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorConduitResultInterface,
    PhabricatorDestructibleInterface {

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
    return $this->getBuildStatusObject()->isBuilding();
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

      'buildable.phid' => null,
      'buildable.object.phid' => null,
      'buildable.container.phid' => null,
      'build.phid' => null,
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

    $results['buildable.phid'] = $buildable->getPHID();
    $results['buildable.object.phid'] = $object->getPHID();
    $results['buildable.container.phid'] = $buildable->getContainerPHID();
    $results['build.phid'] = $this->getPHID();

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
      'buildable.phid' => pht(
        'The object PHID of the Harbormaster Buildable being built.'),
      'buildable.object.phid' => pht(
        'The object PHID of the object (usually a diff or commit) '.
        'being built.'),
      'buildable.container.phid' => pht(
        'The object PHID of the container (usually a revision or repository) '.
        'for the object being built.'),
      'build.phid' => pht(
        'The object PHID of the Harbormaster Build being built.'),
    );

    foreach ($objects as $object) {
      $variables[] = $object->getAvailableBuildVariables();
    }

    $variables = array_mergev($variables);
    return $variables;
  }

  public function isComplete() {
    return $this->getBuildStatusObject()->isComplete();
  }

  public function isPaused() {
    return $this->getBuildStatusObject()->isPaused();
  }

  public function isPassed() {
    return $this->getBuildStatusObject()->isPassed();
  }

  public function isFailed() {
    return $this->getBuildStatusObject()->isFailed();
  }

  public function getURI() {
    $id = $this->getID();
    return "/harbormaster/build/{$id}/";
  }

  protected function getBuildStatusObject() {
    $status_key = $this->getBuildStatus();
    return HarbormasterBuildStatus::newBuildStatusObject($status_key);
  }

  public function getObjectName() {
    return pht('Build %d', $this->getID());
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
    try {
      $this->assertCanRestartBuild();
      return true;
    } catch (HarbormasterRestartException $ex) {
      return false;
    }
  }

  public function assertCanRestartBuild() {
    if ($this->isAutobuild()) {
      throw new HarbormasterRestartException(
        pht('Can Not Restart Autobuild'),
        pht(
          'This build can not be restarted because it is an automatic '.
          'build.'));
    }

    $restartable = HarbormasterBuildPlanBehavior::BEHAVIOR_RESTARTABLE;
    $plan = $this->getBuildPlan();

    // See T13526. Users who can't see the "BuildPlan" can end up here with
    // no object. This is highly questionable.
    if (!$plan) {
      throw new HarbormasterRestartException(
        pht('No Build Plan Permission'),
        pht(
          'You can not restart this build because you do not have '.
          'permission to access the build plan.'));
    }

    $option = HarbormasterBuildPlanBehavior::getBehavior($restartable)
      ->getPlanOption($plan);
    $option_key = $option->getKey();

    $never_restartable = HarbormasterBuildPlanBehavior::RESTARTABLE_NEVER;
    $is_never = ($option_key === $never_restartable);
    if ($is_never) {
      throw new HarbormasterRestartException(
        pht('Build Plan Prevents Restart'),
        pht(
          'This build can not be restarted because the build plan is '.
          'configured to prevent the build from restarting.'));
    }

    $failed_restartable = HarbormasterBuildPlanBehavior::RESTARTABLE_IF_FAILED;
    $is_failed = ($option_key === $failed_restartable);
    if ($is_failed) {
      if (!$this->isFailed()) {
        throw new HarbormasterRestartException(
          pht('Only Restartable if Failed'),
          pht(
            'This build can not be restarted because the build plan is '.
            'configured to prevent the build from restarting unless it '.
            'has failed, and it has not failed.'));
      }
    }

    if ($this->isRestarting()) {
      throw new HarbormasterRestartException(
        pht('Already Restarting'),
        pht(
          'This build is already restarting. You can not reissue a restart '.
          'command to a restarting build.'));
    }
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
    $plan = $this->getBuildPlan();

    // See T13526. Users without permission to access the build plan can
    // currently end up here with no "BuildPlan" object.
    if (!$plan) {
      return false;
    }

    $need_edit = true;
    switch ($command) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
      case HarbormasterBuildCommand::COMMAND_PAUSE:
      case HarbormasterBuildCommand::COMMAND_RESUME:
      case HarbormasterBuildCommand::COMMAND_ABORT:
        if ($plan->canRunWithoutEditCapability()) {
          $need_edit = false;
        }
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
        $plan,
        PhabricatorPolicyCapability::CAN_EDIT);
    }
  }

  public function sendMessage(PhabricatorUser $viewer, $command) {
    // TODO: This should not be an editor transaction, but there are plans to
    // merge BuildCommand into BuildMessage which should moot this. As this
    // exists today, it can race against BuildEngine.

    // This is a bogus content source, but this whole flow should be obsolete
    // soon.
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorConsoleContentSource::SOURCECONST);

    $editor = id(new HarbormasterBuildTransactionEditor())
      ->setActor($viewer)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      $acting_phid = id(new PhabricatorHarbormasterApplication())->getPHID();
      $editor->setActingAsPHID($acting_phid);
    }

    $xaction = id(new HarbormasterBuildTransaction())
      ->setTransactionType(HarbormasterBuildTransaction::TYPE_COMMAND)
      ->setNewValue($command);

    $editor->applyTransactions($this, array($xaction));
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildTransaction();
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $viewer = $engine->getViewer();

    $this->openTransaction();
      $targets = id(new HarbormasterBuildTargetQuery())
        ->setViewer($viewer)
        ->withBuildPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($targets as $target) {
        $engine->destroyObject($target);
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
