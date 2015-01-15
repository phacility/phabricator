<?php

final class PhabricatorMetaMTAMailingList extends PhabricatorMetaMTADAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $email;
  protected $uri;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMailingListListPHIDType::TYPECONST);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'email' => 'text128',
        'uri' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'email' => array(
          'columns' => array('email'),
          'unique' => true,
        ),
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
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
    $this->delete();
    $this->saveTransaction();
  }

}
