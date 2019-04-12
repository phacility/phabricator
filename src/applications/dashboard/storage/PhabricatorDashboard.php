<?php

/**
 * A collection of dashboard panels with a specific layout.
 */
final class PhabricatorDashboard extends PhabricatorDashboardDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorDashboardPanelContainerInterface {

  protected $name;
  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $icon;
  protected $layoutConfig = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  private $panelRefList;

  public static function initializeNewDashboard(PhabricatorUser $actor) {
    return id(new PhabricatorDashboard())
      ->setName('')
      ->setIcon('fa-dashboard')
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy($actor->getPHID())
      ->setStatus(self::STATUS_ACTIVE)
      ->setAuthorPHID($actor->getPHID());
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'layoutConfig' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort255',
        'status' => 'text32',
        'icon' => 'text32',
        'authorPHID' => 'phid',
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorDashboardDashboardPHIDType::TYPECONST;
  }

  public function getRawLayoutMode() {
    $config = $this->getRawLayoutConfig();
    return idx($config, 'layoutMode');
  }

  public function setRawLayoutMode($mode) {
    $config = $this->getRawLayoutConfig();
    $config['layoutMode'] = $mode;
    return $this->setRawLayoutConfig($config);
  }

  public function getRawPanels() {
    $config = $this->getRawLayoutConfig();
    return idx($config, 'panels');
  }

  public function setRawPanels(array $panels) {
    $config = $this->getRawLayoutConfig();
    $config['panels'] = $panels;
    return $this->setRawLayoutConfig($config);
  }

  private function getRawLayoutConfig() {
    $config = $this->getLayoutConfig();

    if (!is_array($config)) {
      $config = array();
    }

    return $config;
  }

  private function setRawLayoutConfig(array $config) {
    // If a cached panel ref list exists, clear it.
    $this->panelRefList = null;

    return $this->setLayoutConfig($config);
  }

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function getURI() {
    return urisprintf('/dashboard/view/%d/', $this->getID());
  }

  public function getObjectName() {
    return pht('Dashboard %d', $this->getID());
  }

  public function getPanelRefList() {
    if (!$this->panelRefList) {
      $this->panelRefList = $this->newPanelRefList();
    }
    return $this->panelRefList;
  }

  private function newPanelRefList() {
    $raw_config = $this->getLayoutConfig();
    return PhabricatorDashboardPanelRefList::newFromDictionary($raw_config);
  }

  public function getPanelPHIDs() {
    $ref_list = $this->getPanelRefList();
    $phids = mpull($ref_list->getPanelRefs(), 'getPanelPHID');
    return array_unique($phids);
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorDashboardTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorDashboardTransaction();
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }

/* -(  PhabricatorDashboardPanelContainerInterface  )------------------------ */

  public function getDashboardPanelContainerPanelPHIDs() {
    return $this->getPanelPHIDs();
  }

/* -(  PhabricatorFulltextInterface  )--------------------------------------- */

  public function newFulltextEngine() {
    return new PhabricatorDashboardFulltextEngine();
  }

/* -(  PhabricatorFerretInterface  )----------------------------------------- */

  public function newFerretEngine() {
    return new PhabricatorDashboardFerretEngine();
  }

}
