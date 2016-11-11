<?php

final class PhabricatorConduitMethodCallLog
  extends PhabricatorConduitDAO
  implements PhabricatorPolicyInterface {

  protected $callerPHID;
  protected $connectionID;
  protected $method;
  protected $error;
  protected $duration;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'auto64',
        'connectionID' => 'id64?',
        'method' => 'text64',
        'error' => 'text255',
        'duration' => 'uint64',
        'callerPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_date' => array(
          'columns' => array('dateCreated'),
        ),
        'key_method' => array(
          'columns' => array('method'),
        ),
        'key_callermethod' => array(
          'columns' => array('callerPHID', 'method'),
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
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
