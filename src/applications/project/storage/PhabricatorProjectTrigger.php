<?php

final class PhabricatorProjectTrigger
  extends PhabricatorProjectDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $ruleset = array();
  protected $editPolicy;

  public static function initializeNewTrigger() {
    $default_edit = PhabricatorPolicies::POLICY_USER;

    return id(new self())
      ->setName('')
      ->setEditPolicy($default_edit);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'ruleset' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorProjectTriggerPHIDType::TYPECONST;
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function getDefaultName() {
    return pht('Custom Trigger');
  }

  public function getURI() {
    return urisprintf(
      '/project/trigger/%d/',
      $this->getID());
  }

  public function getObjectName() {
    return pht('Trigger %d', $this->getID());
  }

  public function getRulesDescription() {
    // TODO: Summarize the trigger rules in human-readable text.
    return pht('Does things.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorProjectTriggerEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProjectTriggerTransaction();
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
        return PhabricatorPolicies::getMostOpenPolicy();
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

    $this->openTransaction();
      $conn = $this->establishConnection('w');

      // Remove the reference to this trigger from any columns which use it.
      queryfx(
        $conn,
        'UPDATE %R SET triggerPHID = null WHERE triggerPHID = %s',
        new PhabricatorProjectColumn(),
        $this->getPHID());

      $this->delete();

    $this->saveTransaction();
  }

}
