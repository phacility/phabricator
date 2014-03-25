<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;

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
   * The build has been stopped.
   */
  const STATUS_STOPPED = 'stopped';

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(self::STATUS_INACTIVE);
  }

  public function delete() {
    $this->openTransaction();
      $this->deleteUnprocessedCommands();
      $result = parent::delete();
    $this->saveTransaction();

    return $result;
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

  public function getBuildTargets() {
    return $this->assertAttached($this->buildTargets);
  }

  public function attachBuildTargets(array $targets) {
    $this->buildTargets = $targets;
    return $this;
  }

  public function isBuilding() {
    return $this->getBuildStatus() === self::STATUS_PENDING ||
      $this->getBuildStatus() === self::STATUS_WAITING ||
      $this->getBuildStatus() === self::STATUS_BUILDING;
  }

  public function createLog(
    HarbormasterBuildTarget $build_target,
    $log_source,
    $log_type) {

    $log_source = phutil_utf8_shorten($log_source, 250);

    $log = HarbormasterBuildLog::initializeNewBuildLog($build_target)
      ->setLogSource($log_source)
      ->setLogType($log_type)
      ->save();

    return $log;
  }

  public function createArtifact(
    HarbormasterBuildTarget $build_target,
    $artifact_key,
    $artifact_type) {

    $artifact =
      HarbormasterBuildArtifact::initializeNewBuildArtifact($build_target);
    $artifact->setArtifactKey($this->getPHID(), $artifact_key);
    $artifact->setArtifactType($artifact_type);
    $artifact->save();
    return $artifact;
  }

  public function loadArtifact($name) {
    $artifact = id(new HarbormasterBuildArtifactQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withArtifactKeys(
        $this->getPHID(),
        array($name))
      ->executeOne();
    if ($artifact === null) {
      throw new Exception("Artifact not found!");
    }
    return $artifact;
  }

  public function retrieveVariablesFromBuild() {
    $results = array(
      'buildable.diff' => null,
      'buildable.revision' => null,
      'buildable.commit' => null,
      'repository.callsign' => null,
      'repository.vcs' => null,
      'repository.uri' => null,
      'step.timestamp' => null,
      'build.id' => null,
    );

    $buildable = $this->getBuildable();
    $object = $buildable->getBuildableObject();

    $repo = null;
    if ($object instanceof DifferentialDiff) {
      $results['buildable.diff'] = $object->getID();
      $revision = $object->getRevision();
      $results['buildable.revision'] = $revision->getID();
      $repo = $revision->getRepository();
    } else if ($object instanceof PhabricatorRepositoryCommit) {
      $results['buildable.commit'] = $object->getCommitIdentifier();
      $repo = $object->getRepository();
    }

    if ($repo) {
      $results['repository.callsign'] = $repo->getCallsign();
      $results['repository.vcs'] = $repo->getVersionControlSystem();
      $results['repository.uri'] = $repo->getPublicCloneURI();
    }

    $results['step.timestamp'] = time();
    $results['build.id'] = $this->getID();

    return $results;
  }

  public static function getAvailableBuildVariables() {
    return array(
      'buildable.diff' =>
        pht('The differential diff ID, if applicable.'),
      'buildable.revision' =>
        pht('The differential revision ID, if applicable.'),
      'buildable.commit' => pht('The commit identifier, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository in Phabricator.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
      'step.timestamp' => pht('The current UNIX timestamp.'),
      'build.id' => pht('The ID of the current build.'),
      'target.phid' => pht('The PHID of the current build target.'),
    );
  }

  public function isComplete() {
    switch ($this->getBuildStatus()) {
      case self::STATUS_PASSED:
      case self::STATUS_FAILED:
      case self::STATUS_ERROR:
      case self::STATUS_STOPPED:
        return true;
    }

    return false;
  }

  public function isStopped() {
    return ($this->getBuildStatus() == self::STATUS_STOPPED);
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
    return !$this->isRestarting();
  }

  public function canStopBuild() {
    return !$this->isComplete() &&
           !$this->isStopped() &&
           !$this->isStopping();
  }

  public function canResumeBuild() {
    return $this->isStopped() &&
           !$this->isResuming();
  }

  public function isStopping() {
    $is_stopping = false;
    foreach ($this->getUnprocessedCommands() as $command_object) {
      $command = $command_object->getCommand();
      switch ($command) {
        case HarbormasterBuildCommand::COMMAND_STOP:
          $is_stopping = true;
          break;
        case HarbormasterBuildCommand::COMMAND_RESUME:
        case HarbormasterBuildCommand::COMMAND_RESTART:
          $is_stopping = false;
          break;
      }
    }

    return $is_stopping;
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
        case HarbormasterBuildCommand::COMMAND_STOP:
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

  public function deleteUnprocessedCommands() {
    foreach ($this->getUnprocessedCommands() as $key => $command_object) {
      $command_object->delete();
      unset($this->unprocessedCommands[$key]);
    }

    return $this;
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
