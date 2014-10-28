<?php

final class AlmanacBinding
  extends AlmanacDAO
  implements PhabricatorPolicyInterface {

  protected $servicePHID;
  protected $devicePHID;
  protected $interfacePHID;
  protected $mailKey;

  private $service = self::ATTACHABLE;
  private $device = self::ATTACHABLE;
  private $interface = self::ATTACHABLE;

  public static function initializeNewBinding(AlmanacService $service) {
    return id(new AlmanacBinding())
      ->setServicePHID($service->getPHID());
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'mailKey' => 'bytes20',
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
    return array(
      pht('A binding inherits the policies of its service.'),
      pht(
        'To view a binding, you must also be able to view its device and '.
        'interface.'),
    );
  }

}
