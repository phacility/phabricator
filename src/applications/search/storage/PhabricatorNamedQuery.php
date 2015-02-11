<?php

final class PhabricatorNamedQuery extends PhabricatorSearchDAO
  implements PhabricatorPolicyInterface {

  protected $queryKey;
  protected $queryName;
  protected $userPHID;
  protected $engineClassName;

  protected $isBuiltin  = 0;
  protected $isDisabled = 0;
  protected $sequence   = 0;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'engineClassName' => 'text128',
        'queryName' => 'text255',
        'queryKey' => 'text12',
        'isBuiltin' => 'bool',
        'isDisabled' => 'bool',
        'sequence' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_userquery' => array(
          'columns' => array('userPHID', 'engineClassName', 'queryKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getSortKey() {
    return sprintf('~%010d%010d', $this->sequence, $this->getID());
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
    if ($viewer->getPHID() == $this->userPHID) {
      return true;
    }
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The queries you have saved are private. Only you can view or edit '.
      'them.');
  }

}
