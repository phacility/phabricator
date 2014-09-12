<?php

final class FundBacking extends FundDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $initiativePHID;
  protected $backerPHID;
  protected $purchasePHID;
  protected $amountInCents;
  protected $status;
  protected $properties = array();

  private $initiative = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(FundBackingPHIDType::TYPECONST);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // If we have the initiative, use the initiative's policy.
        // Otherwise, return NOONE. This allows the backer to continue seeing
        // a backing even if they're no longer allowed to see the initiative.

        $initiative = $this->getInitiative();
        if ($initiative) {
          return $initiative->getPolicy($capability);
        }
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
    return new FundBackingEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new FundBackingTransaction();
  }

}
