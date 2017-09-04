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

  const SCOPE_GLOBAL = 'scope.global';

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

  public function isGlobal() {
    if ($this->getIsBuiltin()) {
      return true;
    }

    if ($this->getUserPHID() === self::SCOPE_GLOBAL) {
      return true;
    }

    return false;
  }

  public function getNamedQuerySortVector() {
    if (!$this->isGlobal()) {
      $phase = 0;
    } else {
      $phase = 1;
    }

    return id(new PhutilSortVector())
      ->addInt($phase)
      ->addInt($this->sequence)
      ->addInt($this->getID());
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($viewer->getPHID() == $this->getUserPHID()) {
      return true;
    }

    if ($this->isGlobal()) {
      switch ($capability) {
        case PhabricatorPolicyCapability::CAN_VIEW:
          return true;
        case PhabricatorPolicyCapability::CAN_EDIT:
          return $viewer->getIsAdmin();
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The queries you have saved are private. Only you can view or edit '.
      'them.');
  }

}
