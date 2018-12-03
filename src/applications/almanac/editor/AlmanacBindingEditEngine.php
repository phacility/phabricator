<?php

final class AlmanacBindingEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.binding';

  private $service;

  public function setService(AlmanacService $service) {
    $this->service = $service;
    return $this;
  }

  public function getService() {
    if (!$this->service) {
      throw new PhutilInvalidStateException('setService');
    }
    return $this->service;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Bindings');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Binding Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac bindings.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    $service = $this->getService();
    return AlmanacBinding::initializeNewBinding($service);
  }

  protected function newEditableObjectForDocumentation() {
    $service_type = AlmanacCustomServiceType::SERVICETYPE;
    $service = AlmanacService::initializeNewService($service_type);
    $this->setService($service);
    return $this->newEditableObject();
  }

  protected function newEditableObjectFromConduit(array $raw_xactions) {
    $service_phid = null;
    foreach ($raw_xactions as $raw_xaction) {
      if ($raw_xaction['type'] !== 'service') {
        continue;
      }

      $service_phid = $raw_xaction['value'];
    }

    if ($service_phid === null) {
      throw new Exception(
        pht(
          'When creating a new Almanac binding via the Conduit API, you '.
          'must provide a "service" transaction to select a service to bind.'));
    }

    $service = id(new AlmanacServiceQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($service_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$service) {
      throw new Exception(
        pht(
          'Service "%s" is unrecognized, restricted, or you do not have '.
          'permission to edit it.',
          $service_phid));
    }

    $this->setService($service);

    return $this->newEditableObject();
  }

  protected function newObjectQuery() {
    return id(new AlmanacBindingQuery())
      ->needProperties(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Binding');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Binding');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Binding');
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Binding');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Binding');
  }

  protected function getObjectName() {
    return pht('Binding');
  }

  protected function getEditorURI() {
    return '/almanac/binding/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/binding/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('service')
        ->setLabel(pht('Service'))
        ->setIsFormField(false)
        ->setTransactionType(
          AlmanacBindingServiceTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Service to create a binding for.'))
        ->setConduitDescription(pht('Select the service to bind.'))
        ->setConduitTypeDescription(pht('Service PHID.'))
        ->setValue($object->getServicePHID()),
      id(new PhabricatorTextEditField())
        ->setKey('interface')
        ->setLabel(pht('Interface'))
        ->setIsFormField(false)
        ->setTransactionType(
          AlmanacBindingInterfaceTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Interface to bind the service to.'))
        ->setConduitDescription(pht('Set the interface to bind.'))
        ->setConduitTypeDescription(pht('Interface PHID.'))
        ->setValue($object->getInterfacePHID()),
      id(new PhabricatorBoolEditField())
        ->setKey('disabled')
        ->setLabel(pht('Disabled'))
        ->setIsFormField(false)
        ->setTransactionType(
          AlmanacBindingDisableTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Disable or enable the binding.'))
        ->setConduitDescription(pht('Disable or enable the binding.'))
        ->setConduitTypeDescription(pht('True to disable the binding.'))
        ->setValue($object->getIsDisabled())
        ->setOptions(
          pht('Enable Binding'),
          pht('Disable Binding')),
    );
  }

}
