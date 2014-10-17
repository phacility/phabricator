<?php

final class AlmanacService
  extends AlmanacDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $nameIndex;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;

  public static function initializeNewService() {
    return id(new AlmanacService())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('nameIndex'),
          'unique' => true,
        ),
        'key_nametext' => array(
          'columns' => array('name'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(AlmanacServicePHIDType::TYPECONST);
  }

  public function save() {
    self::validateServiceName($this->getName());
    $this->nameIndex = PhabricatorHash::digestForIndex($this->getName());

    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    return parent::save();
  }

  public function getURI() {
    return '/almanac/service/view/'.$this->getName().'/';
  }

  public static function validateServiceName($name) {
    if (strlen($name) < 3) {
      throw new Exception(
        pht('Almanac service names must be at least 3 characters long.'));
    }

    if (!preg_match('/^[a-z0-9.-]+\z/', $name)) {
      throw new Exception(
        pht(
          'Almanac service names may only contain lowercase letters, numbers, '.
          'hyphens, and periods.'));
    }

    if (preg_match('/(^|\\.)\d+(\z|\\.)/', $name)) {
      throw new Exception(
        pht(
          'Almanac service names may not have any segments containing only '.
          'digits.'));
    }

    if (preg_match('/\.\./', $name)) {
      throw new Exception(
        pht(
          'Almanac service names may not contain multiple consecutive '.
          'periods.'));
    }

    if (preg_match('/\\.-|-\\./', $name)) {
      throw new Exception(
        pht(
          'Amanac service names may not contain hyphens adjacent to periods.'));
    }

    if (preg_match('/--/', $name)) {
      throw new Exception(
        pht(
          'Almanac service names may not contain multiple consecutive '.
          'hyphens.'));
    }

    if (!preg_match('/^[a-z0-9].*[a-z0-9]\z/', $name)) {
      throw new Exception(
        pht(
          'Almanac service names must begin and end with a letter or number.'));
    }
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
