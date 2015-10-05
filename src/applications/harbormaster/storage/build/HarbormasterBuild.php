<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;
  protected $buildGeneration;
  protected $buildParameters = array();
  protected $planAutoKey;

  private $buildable = self::ATTACHABLE;
  private $buildPlan = self::ATTACHABLE;
  private $buildTargets = self::ATTACHABLE;
  private $unprocessedCommands = self::ATTACHABLE;

  /**
   * Not currently being built.
   */
  const STATUS_INACTIVE = 'inactive';

  /**
   * Pending pick up by the Harbormaster daemon.
   */
  const STATUS_PENDING = 'pending';

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
   * The build has aborted.
   */
  const STATUS_ABORTED = 'aborted';

  /**
   * The build encountered an unexpected error.
   */
  const STATUS_ERROR = 'error';

  /**
   * The build has been paused.
   */
  const STATUS_PAUSED = 'paused';

  /**
   * The build has been deadlocked.
   */
  const STATUS_DEADLOCKED = 'deadlocked';


  /**
   * Get a human readable name for a build status constant.
   *
   * @param  const  Build status constant.
   * @return string Human-readable name.
   */
  public static function getBuildStatusName($status) {
    switch ($status) {
      case self::STATUS_INACTIVE:
        return pht('Inactive');
      case self::STATUS_PENDING:
        return pht('Pending');
      case self::STATUS_BUILDING:
        return pht('Building');
      case self::STATUS_PASSED:
        return pht('Passed');
      case self::STATUS_FAILED:
        return pht('Failed');
      case self::STATUS_ABORTED:
        return pht('Aborted');
      case self::STATUS_ERROR:
        return pht('Unexpected Error');
      case self::STATUS_PAUSED:
        return pht('Paused');
      case self::STATUS_DEADLOCKED:
        return pht('Deadlocked');
      default:
        return pht('Unknown');
    }
  }

  public static function getBuildStatusIcon($status) {
    switch ($status) {
      case self::STATUS_INACTIVE:
      case self::STATUS_PENDING:
        return PHUIStatusItemView::ICON_OPEN;
      case self::STATUS_BUILDING:
        return PHUIStatusItemView::ICON_RIGHT;
      case self::STATUS_PASSED:
        return PHUIStatusItemView::ICON_ACCEPT;
      case self::STATUS_FAILED:
        return PHUIStatusItemView::ICON_REJECT;
      case self::STATUS_ABORTED:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_ERROR:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_PAUSED:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_DEADLOCKED:
        return PHUIStatusItemView::ICON_WARNING;
      default:
        return PHUIStatusItemView::ICON_QUESTION;
    }
  }

  public static function getBuildStatusColor($status) {
    switch ($status) {
      case self::STATUS_INACTIVE:
        return 'dark';
      case self::STATUS_PENDING:
      case self::STATUS_BUILDING:
        return 'blue';
      case self::STATUS_PASSED:
        return 'green';
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
      case self::STATUS_ERROR:
      case self::STATUS_DEADLOCKED:
        return 'red';
      case self::STATUS_PAUSED:
        return 'dark';
      default:
        return 'bluegrey';
    }
  }

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(self::STATUS_INACTIVE)
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
    return $this->getBuildStatus() === self::STATUS_PENDING ||
      $this->getBuildStatus() === self::STATUS_BUILDING;
  }

  public function isAutobuild() {
    return ($this->getPlanAutoKey() !== null);
  }

  public function createLog(
    HarbormasterBuildTarget $build_target,
    $log_source,
    $log_type) {

    $log_source = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(250)
      ->truncateString($log_source);

    $log = HarbormasterBuildLog::initializeNewBuildLog($build_target)
      ->setLogSource($log_source)
      ->setLogType($log_type)
      ->save();

    return $log;
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
    );

    foreach ($objects as $object) {
      $variables[] = $object->getAvailableBuildVariables();
    }

    $variables = array_mergev($variables);
    return $variables;
  }

  public function isComplete() {
    switch ($this->getBuildStatus()) {
      case self::STATUS_PASSED:
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
      case self::STATUS_ERROR:
      case self::STATUS_PAUSED:
        return true;
    }

    return false;
  }

  public function isPaused() {
    return ($this->getBuildStatus() == self::STATUS_PAUSED);
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

}
