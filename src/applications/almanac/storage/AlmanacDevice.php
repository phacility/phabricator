<?php

final class AlmanacDevice
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface,
    PhabricatorSSHPublicKeyInterface,
    AlmanacPropertyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorNgramsInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $nameIndex;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $isLocked;

  private $almanacProperties = self::ATTACHABLE;

  public static function initializeNewDevice() {
    return id(new AlmanacDevice())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
      ->attachAlmanacProperties(array())
      ->setIsLocked(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
        'mailKey' => 'bytes20',
        'isLocked' => 'bool',
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
    return PhabricatorPHID::generateNewPHID(AlmanacDevicePHIDType::TYPECONST);
  }

  public function save() {
    AlmanacNames::validateName($this->getName());

    $this->nameIndex = PhabricatorHash::digestForIndex($this->getName());

    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    return parent::save();
  }

  public function getURI() {
    return '/almanac/device/view/'.$this->getName().'/';
  }


  /**
   * Find locked services which are bound to this device, updating the device
   * lock flag if necessary.
   *
   * @return list<phid> List of locking service PHIDs.
   */
  public function rebuildDeviceLocks() {
    $services = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDevicePHIDs(array($this->getPHID()))
      ->withLocked(true)
      ->execute();

    $locked = (bool)count($services);

    if ($locked != $this->getIsLocked()) {
      $this->setIsLocked((int)$locked);
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        queryfx(
          $this->establishConnection('w'),
          'UPDATE %T SET isLocked = %d WHERE id = %d',
          $this->getTableName(),
          $this->getIsLocked(),
          $this->getID());
      unset($unguarded);
    }

    return $this;
  }


/* -(  AlmanacPropertyInterface  )------------------------------------------- */


  public function attachAlmanacProperties(array $properties) {
    assert_instances_of($properties, 'AlmanacProperty');
    $this->almanacProperties = mpull($properties, null, 'getFieldName');
    return $this;
  }

  public function getAlmanacProperties() {
    return $this->assertAttached($this->almanacProperties);
  }

  public function hasAlmanacProperty($key) {
    $this->assertAttached($this->almanacProperties);
    return isset($this->almanacProperties[$key]);
  }

  public function getAlmanacProperty($key) {
    return $this->assertAttachedKey($this->almanacProperties, $key);
  }

  public function getAlmanacPropertyValue($key, $default = null) {
    if ($this->hasAlmanacProperty($key)) {
      return $this->getAlmanacProperty($key)->getFieldValue();
    } else {
      return $default;
    }
  }

  public function getAlmanacPropertyFieldSpecifications() {
    return array();
  }

  public function newAlmanacPropertyEditEngine() {
    return new AlmanacDevicePropertyEditEngine();
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
        if ($this->getIsLocked()) {
          return PhabricatorPolicies::POLICY_NOONE;
        } else {
          return $this->getEditPolicy();
        }
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    if ($capability === PhabricatorPolicyCapability::CAN_EDIT) {
      if ($this->getIsLocked()) {
        return pht(
          'This device is bound to a locked service, so it can not '.
          'be edited.');
      }
    }

    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacDeviceEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacDeviceTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorSSHPublicKeyInterface  )----------------------------------- */


  public function getSSHPublicKeyManagementURI(PhabricatorUser $viewer) {
    return $this->getURI();
  }

  public function getSSHKeyDefaultName() {
    return $this->getName();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setViewer($engine->getViewer())
      ->withDevicePHIDs(array($this->getPHID()))
      ->execute();
    foreach ($interfaces as $interface) {
      $engine->destroyObject($interface);
    }

    $this->delete();
  }


/* -(  PhabricatorNgramInterface  )------------------------------------------ */


  public function newNgrams() {
    return array(
      id(new AlmanacDeviceNameNgrams())
        ->setValue($this->getName()),
    );
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the device.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new AlmanacPropertiesSearchEngineAttachment())
        ->setAttachmentKey('properties'),
    );
  }

}
