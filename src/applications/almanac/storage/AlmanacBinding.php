<?php

final class AlmanacBinding
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    AlmanacPropertyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorExtendedPolicyInterface {

  protected $servicePHID;
  protected $devicePHID;
  protected $interfacePHID;
  protected $mailKey;
  protected $isDisabled;

  private $service = self::ATTACHABLE;
  private $device = self::ATTACHABLE;
  private $interface = self::ATTACHABLE;
  private $almanacProperties = self::ATTACHABLE;

  public static function initializeNewBinding(AlmanacService $service) {
    return id(new AlmanacBinding())
      ->setServicePHID($service->getPHID())
      ->attachAlmanacProperties(array())
      ->setIsDisabled(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'mailKey' => 'bytes20',
        'isDisabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_service' => array(
          'columns' => array('servicePHID', 'interfacePHID'),
          'unique' => true,
        ),
        'key_device' => array(
          'columns' => array('devicePHID'),
        ),
        'key_interface' => array(
          'columns' => array('interfacePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(AlmanacBindingPHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }
    return parent::save();
  }

  public function getName() {
    return pht('Binding %s', $this->getID());
  }

  public function getURI() {
    return '/almanac/binding/'.$this->getID().'/';
  }

  public function getService() {
    return $this->assertAttached($this->service);
  }

  public function attachService(AlmanacService $service) {
    $this->service = $service;
    return $this;
  }

  public function getDevice() {
    return $this->assertAttached($this->device);
  }

  public function attachDevice(AlmanacDevice $device) {
    $this->device = $device;
    return $this;
  }

  public function getInterface() {
    return $this->assertAttached($this->interface);
  }

  public function attachInterface(AlmanacInterface $interface) {
    $this->interface = $interface;
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
    return new AlmanacBindingPropertyEditEngine();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getService()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getService()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    $notes = array(
      pht('A binding inherits the policies of its service.'),
      pht(
        'To view a binding, you must also be able to view its device and '.
        'interface.'),
    );

    return $notes;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->getService()->isClusterService()) {
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
    return new AlmanacBindingEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacBindingTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->delete();
  }


}
