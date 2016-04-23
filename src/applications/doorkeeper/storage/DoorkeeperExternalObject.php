<?php

final class DoorkeeperExternalObject extends DoorkeeperDAO
  implements PhabricatorPolicyInterface {

  protected $objectKey;
  protected $applicationType;
  protected $applicationDomain;
  protected $objectType;
  protected $objectID;
  protected $objectURI;
  protected $importerPHID;
  protected $properties = array();
  protected $viewPolicy;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectKey' => 'bytes12',
        'applicationType' => 'text32',
        'applicationDomain' => 'text32',
        'objectType' => 'text32',
        'objectID' => 'text64',
        'objectURI' => 'text128?',
        'importerPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectKey'),
          'unique' => true,
        ),
        'key_full' => array(
          'columns' => array(
            'applicationType',
            'applicationDomain',
            'objectType',
            'objectID',
          ),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DoorkeeperExternalObjectPHIDType::TYPECONST);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getObjectKey() {
    $key = parent::getObjectKey();
    if ($key === null) {
      $key = $this->getRef()->getObjectKey();
    }
    return $key;
  }

  public function getRef() {
    return id(new DoorkeeperObjectRef())
      ->setApplicationType($this->getApplicationType())
      ->setApplicationDomain($this->getApplicationDomain())
      ->setObjectType($this->getObjectType())
      ->setObjectID($this->getObjectID());
  }

  public function save() {
    if (!$this->objectKey) {
      $this->objectKey = $this->getObjectKey();
    }

    return parent::save();
  }

  public function setDisplayName($display_name) {
    return $this->setProperty('xobj.name.display', $display_name);
  }

  public function getDisplayName() {
    return $this->getProperty('xobj.name.display', pht('External Object'));
  }

  public function setDisplayFullName($full_name) {
    return $this->setProperty('xobj.name.display-full', $full_name);
  }

  public function getDisplayFullName() {
    $full_name = $this->getProperty('xobj.name.display-full');

    if ($full_name !== null) {
      return $full_name;
    }

    return $this->getDisplayName();
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->viewPolicy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
