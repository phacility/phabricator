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
    PhabricatorConduitResultInterface,
    PhabricatorExtendedPolicyInterface {

  protected $name;
  protected $nameIndex;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;
  protected $isBoundToClusterService;

  private $almanacProperties = self::ATTACHABLE;

  public static function initializeNewDevice() {
    return id(new AlmanacDevice())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
      ->attachAlmanacProperties(array())
      ->setIsBoundToClusterService(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'nameIndex' => 'bytes12',
        'mailKey' => 'bytes20',
        'isBoundToClusterService' => 'bool',
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

  public function rebuildClusterBindingStatus() {
    $services = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDevicePHIDs(array($this->getPHID()))
      ->execute();

    $is_cluster = false;
    foreach ($services as $service) {
      if ($service->isClusterService()) {
        $is_cluster = true;
        break;
      }
    }

    if ($is_cluster != $this->getIsBoundToClusterService()) {
      $this->setIsBoundToClusterService((int)$is_cluster);
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        queryfx(
          $this->establishConnection('w'),
          'UPDATE %T SET isBoundToClusterService = %d WHERE id = %d',
          $this->getTableName(),
          $this->getIsBoundToClusterService(),
          $this->getID());
      unset($unguarded);
    }

    return $this;
  }

  public function isClusterDevice() {
    return $this->getIsBoundToClusterService();
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
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->isClusterDevice()) {
          return array(
            array(
              new PhabricatorAlmanacApplication(),
              AlmanacManageClusterServicesCapability::CAPABILITY,
            ),
          );
        }
        break;
    }

    return array();
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

  public function getSSHKeyNotifyPHIDs() {
    // Devices don't currently have anyone useful to notify about SSH key
    // edits, and they're usually a difficult vector to attack since you need
    // access to a cluster host. However, it would be nice to make them
    // subscribable at some point.
    return array();
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


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


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
