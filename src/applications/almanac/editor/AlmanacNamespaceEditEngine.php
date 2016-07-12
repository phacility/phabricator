<?php

final class AlmanacNamespaceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'almanac.namespace';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Namespaces');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Namespace Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac namespaces.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    return AlmanacNamespace::initializeNewNamespace();
  }

  protected function newObjectQuery() {
    return new AlmanacNamespaceQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Namespace');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Namespace');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Namespace: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Namespace');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Namespace');
  }

  protected function getObjectName() {
    return pht('Namespace');
  }

  protected function getEditorURI() {
    return '/almanac/namespace/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/almanac/namespace/';
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/almanac/namespace/{$id}/";
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      AlmanacCreateNamespacesCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the namespace.'))
        ->setTransactionType(AlmanacNamespaceTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
