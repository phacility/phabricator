<?php

final class ReleephProject extends ReleephDAO
  implements PhabricatorPolicyInterface {

  const DEFAULT_BRANCH_NAMESPACE = 'releeph-releases';
  const SYSTEM_AGENT_USERNAME_PREFIX = 'releeph-agent-';

  const COMMIT_AUTHOR_NONE      = 'commit-author-none';
  const COMMIT_AUTHOR_FROM_DIFF = 'commit-author-is-from-diff';
  const COMMIT_AUTHOR_REQUESTOR = 'commit-author-is-requestor';

  protected $phid;
  protected $name;

  // Specifying the place to pick from is a requirement for svn, though not
  // for git.  It's always useful though for reasoning about what revs have
  // been picked and which haven't.
  protected $trunkBranch;

  protected $repositoryPHID;
  protected $isActive;
  protected $createdByUserPHID;
  protected $arcanistProjectID;

  protected $details = array();

  private $repository = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(ReleephPHIDTypeProject::TYPECONST);
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function getURI($path = null) {
    $components = array(
      '/releeph/project',
      $this->getID(),
      $path
    );
    return implode('/', $components);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function willSaveObject() {
    // Do this first, to generate the PHID
    parent::willSaveObject();

    $banned_names = $this->getBannedNames();
    if (in_array($this->name, $banned_names)) {
      throw new Exception(sprintf(
        "The name '%s' is in the list of banned project names!",
        $this->name,
        implode(', ', $banned_names)));
    }

    if (!$this->getDetail('releaseCounter')) {
      $this->setDetail('releaseCounter', 0);
    }
  }

  public function loadArcanistProject() {
    return $this->loadOneRelative(
      new PhabricatorRepositoryArcanistProject(),
      'id',
      'getArcanistProjectID');
  }

  public function getPushers() {
    return $this->getDetail('pushers', array());
  }

  public function isPusher(PhabricatorUser $user) {
    // TODO Deprecate this once `isPusher` is out of the Facebook codebase.
    return $this->isAuthoritative($user);
  }

  public function isAuthoritative(PhabricatorUser $user) {
    return $this->isAuthoritativePHID($user->getPHID());
  }

  public function isAuthoritativePHID($phid) {
    $pushers = $this->getPushers();
    if (!$pushers) {
      return true;
    } else {
      return in_array($phid, $pushers);
    }
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  // TODO: Remove once everything uses ProjectQuery.
  public function loadPhabricatorRepository() {
    return $this->loadOneRelative(
      new PhabricatorRepository(),
      'phid',
      'getRepositoryPHID');
  }

  public function getCurrentReleaseNumber() {
    $current_release_numbers = array();

    // From the project...
    $current_release_numbers[] = $this->getDetail('releaseCounter', 0);

    // From any branches...
    $branches = id(new ReleephBranch())->loadAllWhere(
      'releephProjectID = %d', $this->getID());
    if ($branches) {
      $release_numbers = array();
      foreach ($branches as $branch) {
        $current_release_numbers[] = $branch->getDetail('releaseNumber', 0);
      }
    }

    return max($current_release_numbers);
  }

  public function getReleephFieldSelector() {
    return new ReleephDefaultFieldSelector();
  }

  /**
   * Wrapper to setIsActive() that logs who deactivated a project
   */
  public function deactivate(PhabricatorUser $actor) {
    return $this
      ->setIsActive(0)
      ->setDetail('last_deactivated_user', $actor->getPHID())
      ->setDetail('last_deactivated_time', time());
  }

  // Hide this from the public
  private function setIsActive($v) {
    return parent::setIsActive($v);
  }

  private function getBannedNames() {
    return array(
      'branch', // no one's tried this... yet!
    );
  }

  public function isTestFile($filename) {
    $test_paths = $this->getDetail('testPaths', array());

    foreach ($test_paths as $test_path) {
      if (preg_match($test_path, $filename)) {
        return true;
      }
    }
    return false;
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
