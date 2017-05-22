<?php

final class NuanceSourceEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'nuance.source';

  private $sourceDefinition;

  public function setSourceDefinition(
    NuanceSourceDefinition $source_definition) {
    $this->sourceDefinition = $source_definition;
    return $this;
  }

  public function getSourceDefinition() {
    return $this->sourceDefinition;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Nuance Sources');
  }

  public function getSummaryHeader() {
    return pht('Edit Nuance Source Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Nuance sources.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();

    $definition = $this->getSourceDefinition();
    if (!$definition) {
      throw new PhutilInvalidStateException('setSourceDefinition');
    }

    return NuanceSource::initializeNewSource(
      $viewer,
      $definition);
  }

  protected function newObjectQuery() {
    return new NuanceSourceQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Source');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Source');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Source: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Source');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Source');
  }

  protected function getObjectName() {
    return pht('Source');
  }

  protected function getEditorURI() {
    return '/nuance/source/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/nuance/source/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the source.'))
        ->setTransactionType(NuanceSourceNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('defaultQueue')
        ->setLabel(pht('Default Queue'))
        ->setDescription(pht('Default queue.'))
        ->setTransactionType(
          NuanceSourceDefaultQueueTransaction::TRANSACTIONTYPE)
        ->setDatasource(new NuanceQueueDatasource())
        ->setSingleValue($object->getDefaultQueuePHID()),
    );
  }

}
