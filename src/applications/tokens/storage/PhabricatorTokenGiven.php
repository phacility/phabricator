<?php

final class PhabricatorTokenGiven extends PhabricatorTokenDAO
  implements PhabricatorPolicyInterface {

  protected $authorPHID;
  protected $objectPHID;
  protected $tokenPHID;

  private $object = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_KEY_SCHEMA => array(
        'key_all' => array(
          'columns' => array('objectPHID', 'authorPHID'),
          'unique' => true,
        ),
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
        'key_token' => array(
          'columns' => array('tokenPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachObject(PhabricatorTokenReceiverInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getObject()->getPolicy($capability);
      default:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getObject()->hasAutomaticCapability(
          $capability,
          $user);
      default:
        if ($user->getPHID() == $this->authorPHID) {
          return true;
        }
        return false;
    }
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht(
          'A token inherits the policies of the object it is awarded to.');
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht(
          'The user who gave a token can always edit it.');
    }
    return null;
  }


}
