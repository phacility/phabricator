<?php

final class ReleephProject extends ReleephDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  const DEFAULT_BRANCH_NAMESPACE = 'releeph-releases';
  const SYSTEM_AGENT_USERNAME_PREFIX = 'releeph-agent-';

  protected $name;

  // Specifying the place to pick from is a requirement for svn, though not
  // for git. It's always useful though for reasoning about what revs have
  // been picked and which haven't.
  protected $trunkBranch;

  protected $repositoryPHID;
  protected $isActive;
  protected $createdByUserPHID;

  protected $details = array();

  private $repository = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'trunkBranch' => 'text255',
        'isActive' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'projectName' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(ReleephProductPHIDType::TYPECONST);
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function getURI($path = null) {
    $components = array(
      '/releeph/product',
      $this->getID(),
      $path,
    );
    return implode('/', $components);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
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

  public function getReleephFieldSelector() {
    return new ReleephDefaultFieldSelector();
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new ReleephProductEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new ReleephProductTransaction();
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
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
