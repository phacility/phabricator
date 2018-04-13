<?php

final class AlmanacNetworkEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.network';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Networks');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Network Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac networks.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    return AlmanacNetwork::initializeNewNetwork();
  }

  protected function newObjectQuery() {
    return new AlmanacNetworkQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Network');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Network');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Network: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Network');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Network');
  }

  protected function getObjectName() {
    return pht('Network');
  }

  protected function getEditorURI() {
    return '/almanac/network/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/network/';
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/almanac/network/{$id}/";
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      AlmanacCreateNetworksCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the network.'))
        ->setTransactionType(AlmanacNetworkNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
