<?php

final class PhabricatorConduitMethodCallLog
  extends PhabricatorConduitDAO
  implements PhabricatorPolicyInterface {

  protected $callerPHID;
  protected $connectionID;
  protected $method;
  protected $error;
  protected $duration;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'id64',
        'connectionID' => 'id64?',
        'method' => 'text255',
        'error' => 'text255',
        'duration' => 'uint64',
        'callerPHID' => 'phid?',
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
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
