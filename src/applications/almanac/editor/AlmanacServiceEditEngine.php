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

  protected function newEditableObjectFromConduit(array $raw_xactions) {
    $type = null;
    foreach ($raw_xactions as $raw_xaction) {
      if ($raw_xaction['type'] !== 'type') {
        continue;
      }

      $type = $raw_xaction['value'];
    }

    if ($type === null) {
      throw new Exception(
        pht(
          'When creating a new Almanac service via the Conduit API, you '.
          'must provide a "type" transaction to select a type.'));
    }

    $map = AlmanacServiceType::getAllServiceTypes();
    if (!isset($map[$type])) {
      throw new Exception(
        pht(
          'Service type "%s" is unrecognized. Valid types are: %s.',
          $type,
          implode(', ', array_keys($map))));
    }

    $this->setServiceType($type);

    return $this->newEditableObject();
  }

  protected function newEditableObjectForDocumentation() {
    $service_type = new AlmanacCustomServiceType();
    $this->setServiceType($service_type->getServiceTypeConstant());
    return $this->newEditableObject();
  }

  protected function newObjectQuery() {
    return id(new AlmanacServiceQuery())
      ->needProperties(true);
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
        ->setTransactionType(AlmanacServiceNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('type')
        ->setLabel(pht('Type'))
        ->setIsFormField(false)
        ->setTransactionType(
          AlmanacServiceTypeTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('When creating a service, set the type.'))
        ->setConduitDescription(pht('Set the service type.'))
        ->setConduitTypeDescription(pht('Service type.'))
        ->setValue($object->getServiceType()),
    );
  }

}
