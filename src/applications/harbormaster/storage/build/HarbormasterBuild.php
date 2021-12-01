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
  private $unprocessedMessages = self::ATTACHABLE;

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(HarbormasterBuildStatus::STATUS_INACTIVE)
      ->setBuildGeneration(0);
  }

  public function delete() {
    $this->openTransaction();
      $this->deleteUnprocessedMessages();
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

  public function isPending() {
    return $this->getBuildstatusObject()->isPending();
  }

  public function getURI() {
    $id = $this->getID();
    return "/harbormaster/build/{$id}/";
  }

  public function getBuildPendingStatusObject() {
    list($pending_status) = $this->getUnprocessedMessageState();

    if ($pending_status !== null) {
      return HarbormasterBuildStatus::newBuildStatusObject($pending_status);
    }

    return $this->getBuildStatusObject();
  }

  protected function getBuildStatusObject() {
    $status_key = $this->getBuildStatus();
    return HarbormasterBuildStatus::newBuildStatusObject($status_key);
  }

  public function getObjectName() {
    return pht('Build %d', $this->getID());
  }


/* -(  Build Messages  )----------------------------------------------------- */


  private function getUnprocessedMessages() {
    return $this->assertAttached($this->unprocessedMessages);
  }

  public function getUnprocessedMessagesForApply() {
    $unprocessed_state = $this->getUnprocessedMessageState();
    list($pending_status, $apply_messages) = $unprocessed_state;

    return $apply_messages;
  }

  private function getUnprocessedMessageState() {
    // NOTE: If a build has multiple unprocessed messages, we'll ignore
    // messages that are obsoleted by a later or stronger message.
    //
    // For example, if a build has both "pause" and "abort" messages in queue,
    // we just ignore the "pause" message and perform an "abort", since pausing
    // first wouldn't affect the final state, so we can just skip it.
    //
    // Likewise, if a build has both "restart" and "abort" messages, the most
    // recent message is controlling: we'll take whichever action a command
    // was most recently issued for.

    $is_restarting = false;
    $is_aborting = false;
    $is_pausing = false;
    $is_resuming = false;

    $apply_messages = array();

    foreach ($this->getUnprocessedMessages() as $message_object) {
      $message_type = $message_object->getType();
      switch ($message_type) {
        case HarbormasterBuildMessageRestartTransaction::MESSAGETYPE:
          $is_restarting = true;
          $is_aborting = false;
          $apply_messages = array($message_object);
          break;
        case HarbormasterBuildMessageAbortTransaction::MESSAGETYPE:
          $is_aborting = true;
          $is_restarting = false;
          $apply_messages = array($message_object);
          break;
        case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
          $is_pausing = true;
          $is_resuming = false;
          $apply_messages = array($message_object);
          break;
        case HarbormasterBuildMessageResumeTransaction::MESSAGETYPE:
          $is_resuming = true;
          $is_pausing = false;
          $apply_messages = array($message_object);
          break;
      }
    }

    $pending_status = null;
    if ($is_restarting) {
      $pending_status = HarbormasterBuildStatus::PENDING_RESTARTING;
    } else if ($is_aborting) {
      $pending_status = HarbormasterBuildStatus::PENDING_ABORTING;
    } else if ($is_pausing) {
      $pending_status = HarbormasterBuildStatus::PENDING_PAUSING;
    } else if ($is_resuming) {
      $pending_status = HarbormasterBuildStatus::PENDING_RESUMING;
    }

    return array($pending_status, $apply_messages);
  }

  public function attachUnprocessedMessages(array $messages) {
    assert_instances_of($messages, 'HarbormasterBuildMessage');
    $this->unprocessedMessages = $messages;
    return $this;
  }

  public function isPausing() {
    return $this->getBuildPendingStatusObject()->isPausing();
  }

  public function isResuming() {
    return $this->getBuildPendingStatusObject()->isResuming();
  }

  public function isRestarting() {
    return $this->getBuildPendingStatusObject()->isRestarting();
  }

  public function isAborting() {
    return $this->getBuildPendingStatusObject()->isAborting();
  }

  public function markUnprocessedMessagesAsProcessed() {
    foreach ($this->getUnprocessedMessages() as $key => $message_object) {
      $message_object
        ->setIsConsumed(1)
        ->save();
    }

    return $this;
  }

  public function deleteUnprocessedMessages() {
    foreach ($this->getUnprocessedMessages() as $key => $message_object) {
      $message_object->delete();
      unset($this->unprocessedMessages[$key]);
    }

    return $this;
  }

  public function sendMessage(PhabricatorUser $viewer, $message_type) {
    HarbormasterBuildMessage::initializeNewMessage($viewer)
      ->setReceiverPHID($this->getPHID())
      ->setType($message_type)
      ->save();

    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $this->getID(),
      ),
      array(
        'objectPHID' => $this->getPHID(),
        'containerPHID' => $this->getBuildablePHID(),
      ));
  }

  public function releaseAllArtifacts(PhabricatorUser $viewer) {
    $targets = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withBuildPHIDs(array($this->getPHID()))
      ->withBuildGenerations(array($this->getBuildGeneration()))
      ->execute();

    if (!$targets) {
      return;
    }

    $target_phids = mpull($targets, 'getPHID');

    $artifacts = id(new HarbormasterBuildArtifactQuery())
      ->setViewer($viewer)
      ->withBuildTargetPHIDs($target_phids)
      ->withIsReleased(false)
      ->execute();
    foreach ($artifacts as $artifact) {
      $artifact->releaseArtifact();
    }
  }

  public function restartBuild(PhabricatorUser $viewer) {
    // TODO: This should become transactional.

    // We're restarting the build, so release all previous artifacts.
    $this->releaseAllArtifacts($viewer);

    // Increment the build generation counter on the build.
    $this->setBuildGeneration($this->getBuildGeneration() + 1);

    // Currently running targets should periodically check their build
    // generation (which won't have changed) against the build's generation.
    // If it is different, they will automatically stop what they're doing
    // and abort.

    // Previously we used to delete targets, logs and artifacts here. Instead,
    // leave them around so users can view previous generations of this build.
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
