<?php

final class AlmanacNetwork
  extends AlmanacDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $viewPolicy;
  protected $editPolicy;

  public static function initializeNewNetwork() {
    return id(new AlmanacNetwork())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
            'columns' => array('name'),
            'unique' => true,
          ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return AlmanacNetworkPHIDType::TYPECONST;
  }

  public function getURI() {
    return urisprintf(
      '/almanac/network/%s/',
      $this->getID());
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacNetworkEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacNetworkTransaction();
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

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setViewer($engine->getViewer())
      ->withNetworkPHIDs(array($this->getPHID()))
      ->execute();

    foreach ($interfaces as $interface) {
      $engine->destroyObject($interface);
    }

    $this->delete();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new AlmanacNetworkNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the network.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
