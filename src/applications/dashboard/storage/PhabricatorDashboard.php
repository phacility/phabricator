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
    PhabricatorProjectInterface {

  protected $name;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $layoutConfig = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  private $panelPHIDs = self::ATTACHABLE;
  private $panels = self::ATTACHABLE;
  private $edgeProjectPHIDs = self::ATTACHABLE;


  public static function initializeNewDashboard(PhabricatorUser $actor) {
    return id(new PhabricatorDashboard())
      ->setName('')
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($actor->getPHID())
      ->setStatus(self::STATUS_ACTIVE)
      ->attachPanels(array())
      ->attachPanelPHIDs(array());
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

  public static function copyDashboard(
    PhabricatorDashboard $dst,
    PhabricatorDashboard $src) {

    $dst->name = $src->name;
    $dst->layoutConfig = $src->layoutConfig;

    return $dst;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'layoutConfig' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'status' => 'text32',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorDashboardDashboardPHIDType::TYPECONST);
  }

  public function getLayoutConfigObject() {
    return PhabricatorDashboardLayoutConfig::newFromDictionary(
      $this->getLayoutConfig());
  }

  public function setLayoutConfigFromObject(
    PhabricatorDashboardLayoutConfig $object) {
    $this->setLayoutConfig($object->toDictionary());
    return $this;
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->edgeProjectPHIDs);
  }

  public function attachProjectPHIDs(array $phids) {
    $this->edgeProjectPHIDs = $phids;
    return $this;
  }

  public function attachPanelPHIDs(array $phids) {
    $this->panelPHIDs = $phids;
    return $this;
  }

  public function getPanelPHIDs() {
    return $this->assertAttached($this->panelPHIDs);
  }

  public function attachPanels(array $panels) {
    assert_instances_of($panels, 'PhabricatorDashboardPanel');
    $this->panels = $panels;
    return $this;
  }

  public function getPanels() {
    return $this->assertAttached($this->panels);
  }

  public function isClosed() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorDashboardTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorDashboardTransaction();
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

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $installs = id(new PhabricatorDashboardInstall())->loadAllWhere(
        'dashboardPHID = %s',
        $this->getPHID());
      foreach ($installs as $install) {
        $install->delete();
      }

      $this->delete();
    $this->saveTransaction();
  }


}
