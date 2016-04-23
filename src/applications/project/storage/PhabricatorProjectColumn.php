<?php

final class PhabricatorProjectColumn
  extends PhabricatorProjectDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorExtendedPolicyInterface {

  const STATUS_ACTIVE = 0;
  const STATUS_HIDDEN = 1;

  const DEFAULT_ORDER = 'natural';
  const ORDER_NATURAL = 'natural';
  const ORDER_PRIORITY = 'priority';

  protected $name;
  protected $status;
  protected $projectPHID;
  protected $proxyPHID;
  protected $sequence;
  protected $properties = array();

  private $project = self::ATTACHABLE;
  private $proxy = self::ATTACHABLE;

  public static function initializeNewColumn(PhabricatorUser $user) {
    return id(new PhabricatorProjectColumn())
      ->setName('')
      ->setStatus(self::STATUS_ACTIVE)
      ->attachProxy(null);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'status' => 'uint32',
        'sequence' => 'uint32',
        'proxyPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_status' => array(
          'columns' => array('projectPHID', 'status', 'sequence'),
        ),
        'key_sequence' => array(
          'columns' => array('projectPHID', 'sequence'),
        ),
        'key_proxy' => array(
          'columns' => array('projectPHID', 'proxyPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProjectColumnPHIDType::TYPECONST);
  }

  public function attachProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->assertAttached($this->project);
  }

  public function attachProxy($proxy) {
    $this->proxy = $proxy;
    return $this;
  }

  public function getProxy() {
    return $this->assertAttached($this->proxy);
  }

  public function isDefaultColumn() {
    return (bool)$this->getProperty('isDefault');
  }

  public function isHidden() {
    $proxy = $this->getProxy();
    if ($proxy) {
      return $proxy->isArchived();
    }

    return ($this->getStatus() == self::STATUS_HIDDEN);
  }

  public function getDisplayName() {
    $proxy = $this->getProxy();
    if ($proxy) {
      return $proxy->getProxyColumnName();
    }

    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    if ($this->isDefaultColumn()) {
      return pht('Backlog');
    }

    return pht('Unnamed Column');
  }

  public function getDisplayType() {
    if ($this->isDefaultColumn()) {
      return pht('(Default)');
    }
    if ($this->isHidden()) {
      return pht('(Hidden)');
    }

    return null;
  }

  public function getDisplayClass() {
    $proxy = $this->getProxy();
    if ($proxy) {
      return $proxy->getProxyColumnClass();
    }

    return null;
  }

  public function getHeaderIcon() {
    $proxy = $this->getProxy();
    if ($proxy) {
      return $proxy->getProxyColumnIcon();
    }

    if ($this->isHidden()) {
      return 'fa-eye-slash';
    }

    return null;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getPointLimit() {
    return $this->getProperty('pointLimit');
  }

  public function setPointLimit($limit) {
    $this->setProperty('pointLimit', $limit);
    return $this;
  }

  public function getOrderingKey() {
    $proxy = $this->getProxy();

    // Normal columns and subproject columns go first, in a user-controlled
    // order.

    // All the milestone columns go last, in their sequential order.

    if (!$proxy || !$proxy->isMilestone()) {
      $group = 'A';
      $sequence = $this->getSequence();
    } else {
      $group = 'B';
      $sequence = $proxy->getMilestoneNumber();
    }

    return sprintf('%s%012d', $group, $sequence);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorProjectColumnTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProjectColumnTransaction();
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
    // NOTE: Column policies are enforced as an extended policy which makes
    // them the same as the project's policies.
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Users must be able to see a project to see its board.');
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getProject(), $capability),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

}
