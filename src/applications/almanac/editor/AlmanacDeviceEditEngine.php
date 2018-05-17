<?php

final class AlmanacDeviceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.device';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Devices');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Device Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac devices.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    return AlmanacDevice::initializeNewDevice();
  }

  protected function newObjectQuery() {
    return id(new AlmanacDeviceQuery())
      ->needProperties(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Device');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Device');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Device: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Device');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Device');
  }

  protected function getObjectName() {
    return pht('Device');
  }

  protected function getEditorURI() {
    return '/almanac/device/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/device/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      AlmanacCreateDevicesCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the device.'))
        ->setTransactionType(AlmanacDeviceNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
