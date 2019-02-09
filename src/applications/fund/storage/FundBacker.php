<?php

final class FundBacker extends FundDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $initiativePHID;
  protected $backerPHID;
  protected $amountAsCurrency;
  protected $status;
  protected $properties = array();

  private $initiative = self::ATTACHABLE;

  const STATUS_NEW = 'new';
  const STATUS_IN_CART = 'in-cart';
  const STATUS_PURCHASED = 'purchased';

  public static function initializeNewBacker(PhabricatorUser $actor) {
    return id(new FundBacker())
      ->setBackerPHID($actor->getPHID())
      ->setStatus(self::STATUS_NEW);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'amountAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
        'amountAsCurrency' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_initiative' => array(
          'columns' => array('initiativePHID'),
        ),
        'key_backer' => array(
          'columns' => array('backerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(FundBackerPHIDType::TYPECONST);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getInitiative() {
    return $this->assertAttached($this->initiative);
  }

  public function attachInitiative(FundInitiative $initiative = null) {
    $this->initiative = $initiative;
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
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // If we have the initiative, use the initiative's policy.
        // Otherwise, return NOONE. This allows the backer to continue seeing
        // a backer even if they're no longer allowed to see the initiative.

        $initiative = $this->getInitiative();
        if ($initiative) {
          return $initiative->getPolicy($capability);
        }
        return PhabricatorPolicies::POLICY_NOONE;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getBackerPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht('A backer can always see what they have backed.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new FundBackerEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new FundBackerTransaction();
  }

}
