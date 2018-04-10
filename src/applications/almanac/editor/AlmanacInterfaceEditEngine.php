<?php

final class AlmanacInterfaceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.interface';

  private $device;

  public function setDevice(AlmanacDevice $device) {
    $this->device = $device;
    return $this;
  }

  public function getDevice() {
    if (!$this->device) {
      throw new PhutilInvalidStateException('setDevice');
    }
    return $this->device;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Interfaces');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Interface Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac interfaces.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    $interface = AlmanacInterface::initializeNewInterface();

    $device = $this->getDevice();
    $interface
      ->setDevicePHID($device->getPHID())
      ->attachDevice($device);

    return $interface;
  }

  protected function newObjectQuery() {
    return new AlmanacInterfaceQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Interface');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Interface');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Interface');
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Interface');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Interface');
  }

  protected function getObjectName() {
    return pht('Interface');
  }

  protected function getEditorURI() {
    return '/almanac/interface/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/interface/';
  }

  protected function getObjectViewURI($object) {
    return $object->getDevice()->getURI();
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    // TODO: Some day, this should be a datasource.
    $networks = id(new AlmanacNetworkQuery())
      ->setViewer($viewer)
      ->execute();
    $network_map = mpull($networks, 'getName', 'getPHID');

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('device')
        ->setLabel(pht('Device'))
        ->setIsConduitOnly(true)
        ->setTransactionType(
          AlmanacInterfaceDeviceTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('When creating an interface, set the device.'))
        ->setConduitDescription(pht('Set the device.'))
        ->setConduitTypeDescription(pht('Device PHID.'))
        ->setValue($object->getDevicePHID()),
      id(new PhabricatorSelectEditField())
        ->setKey('network')
        ->setLabel(pht('Network'))
        ->setDescription(pht('Network for the interface.'))
        ->setTransactionType(
          AlmanacInterfaceNetworkTransaction::TRANSACTIONTYPE)
        ->setValue($object->getNetworkPHID())
        ->setOptions($network_map),
      id(new PhabricatorTextEditField())
        ->setKey('address')
        ->setLabel(pht('Address'))
        ->setDescription(pht('Address of the service.'))
        ->setTransactionType(
          AlmanacInterfaceAddressTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getAddress()),
      id(new PhabricatorTextEditField())
        ->setKey('port')
        ->setLabel(pht('Port'))
        ->setDescription(pht('Port of the service.'))
        ->setTransactionType(AlmanacInterfacePortTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getPort()),
    );
  }

}
