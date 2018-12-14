<?php

final class PhabricatorAuthChallenge
  extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $factorPHID;
  protected $sessionPHID;
  protected $challengeKey;
  protected $challengeTTL;
  protected $properties = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'challengeKey' => 'text255',
        'challengeTTL' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_issued' => array(
          'columns' => array('userPHID', 'challengeTTL'),
        ),
        'key_collection' => array(
          'columns' => array('challengeTTL'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorAuthChallengePHIDType::TYPECONST;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() === $this->getUserPHID());
  }

}
