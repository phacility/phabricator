<?php

final class AlmanacInterface
  extends AlmanacDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorConduitResultInterface {

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
        'key_unique' => array(
          'columns' => array('devicePHID', 'networkPHID', 'address', 'port'),
          'unique' => true,
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

  public function loadIsInUse() {
    $binding = id(new AlmanacBindingQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withInterfacePHIDs(array($this->getPHID()))
      ->setLimit(1)
      ->executeOne();

    return (bool)$binding;
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

    return $notes;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->getDevice()->isClusterDevice()) {
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new AlmanacInterfaceEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new AlmanacInterfaceTransaction();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('devicePHID')
        ->setType('phid')
        ->setDescription(pht('The device the interface is on.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('networkPHID')
        ->setType('phid')
        ->setDescription(pht('The network the interface is part of.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('address')
        ->setType('string')
        ->setDescription(pht('The address of the interface.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('port')
        ->setType('int')
        ->setDescription(pht('The port number of the interface.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'devicePHID' => $this->getDevicePHID(),
      'networkPHID' => $this->getNetworkPHID(),
      'address' => (string)$this->getAddress(),
      'port' => (int)$this->getPort(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
