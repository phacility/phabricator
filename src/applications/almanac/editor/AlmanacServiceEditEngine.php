<?php

final class AlmanacServiceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.service';

  private $serviceType;

  public function setServiceType($service_type) {
    $this->serviceType = $service_type;
    return $this;
  }

  public function getServiceType() {
    return $this->serviceType;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Services');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Service Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac services.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    $service_type = $this->getServiceType();
    return AlmanacService::initializeNewService($service_type);
  }

  protected function newObjectQuery() {
    return new AlmanacServiceQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Service');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Service');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Service: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Service');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Service');
  }

  protected function getObjectName() {
    return pht('Service');
  }

  protected function getEditorURI() {
    return '/almanac/service/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/service/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      AlmanacCreateServicesCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the service.'))
        ->setTransactionType(AlmanacServiceTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
