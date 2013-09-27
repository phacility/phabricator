<?php

final class PhabricatorDaemonLog extends PhabricatorDaemonDAO
  implements PhabricatorPolicyInterface {

  const STATUS_UNKNOWN = 'unknown';
  const STATUS_RUNNING = 'run';
  const STATUS_DEAD    = 'dead';
  const STATUS_WAIT    = 'wait';
  const STATUS_EXITED  = 'exit';

  protected $daemon;
  protected $host;
  protected $pid;
  protected $argv;
  protected $status;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'argv' => self::SERIALIZATION_JSON,
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
    return PhabricatorPolicies::POLICY_ADMIN;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
