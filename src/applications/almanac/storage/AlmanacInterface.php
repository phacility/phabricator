<?php

final class AlmanacInterface
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $devicePHID;
  protected $networkPHID;
  protected $address;
  protected $port;

  private $device = self::ATTACHABLE;
  private $network = self::ATTACHABLE;

  public static function initializeNewInterface() {
    return id(new AlmanacInterface());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'address' => 'text64',
        'port' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_location' => array(
          'columns' => array('networkPHID', 'address', 'port'),
        ),
        'key_device' => array(
          'columns' => array('devicePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      AlmanacInterfacePHIDType::TYPECONST);
  }

  public function getDevice() {
    return $this->assertAttached($this->device);
  }

  public function attachDevice(AlmanacDevice $device) {
    $this->device = $device;
    return $this;
  }

  public function getNetwork() {
    return $this->assertAttached($this->network);
  }

  public function attachNetwork(AlmanacNetwork $network) {
    $this->network = $network;
    return $this;
  }

  public function toAddress() {
    return AlmanacAddress::newFromParts(
      $this->getNetworkPHID(),
      $this->getAddress(),
      $this->getPort());
  }

  public function getAddressHash() {
    return $this->toAddress()->toHash();
  }

  public function renderDisplayAddress() {
    return $this->getAddress().':'.$this->getPort();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getDevice()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getDevice()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    $notes = array(
      pht('An interface inherits the policies of the device it belongs to.'),
      pht(
        'You must be able to view the network an interface resides on to '.
        'view the interface.'),
    );

    if ($capability === PhabricatorPolicyCapability::CAN_EDIT) {
      if ($this->getDevice()->getIsLocked()) {
        $notes[] = pht(
          'The device for this interface is locked, so it can not be edited.');
      }
    }

    return $notes;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $bindings = id(new AlmanacBindingQuery())
      ->setViewer($engine->getViewer())
      ->withInterfacePHIDs(array($this->getPHID()))
      ->execute();
    foreach ($bindings as $binding) {
      $engine->destroyObject($binding);
    }

    $this->delete();
  }

}
