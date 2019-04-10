<?php

final class PhabricatorDashboardPortal
  extends PhabricatorDashboardDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface {

  protected $name;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $properties = array();

  public static function initializeNewPortal() {
    return id(new self())
      ->setName('')
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
      ->setStatus(PhabricatorDashboardPortalStatus::STATUS_ACTIVE);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'status' => 'text32',
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorDashboardPortalPHIDType::TYPECONST;
  }

  public function getPortalProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setPortalProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getObjectName() {
    return pht('Portal %d', $this->getID());
  }

  public function getURI() {
    return '/portal/view/'.$this->getID().'/';
  }

  public function isArchived() {
    $status_archived = PhabricatorDashboardPortalStatus::STATUS_ARCHIVED;
    return ($this->getStatus() === $status_archived);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorDashboardPortalEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorDashboardPortalTransaction();
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

/* -(  PhabricatorFulltextInterface  )--------------------------------------- */

  public function newFulltextEngine() {
    return new PhabricatorDashboardPortalFulltextEngine();
  }

/* -(  PhabricatorFerretInterface  )----------------------------------------- */

  public function newFerretEngine() {
    return new PhabricatorDashboardPortalFerretEngine();
  }

}
