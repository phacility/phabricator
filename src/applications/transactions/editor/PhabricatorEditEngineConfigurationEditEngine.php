<?php

final class PhabricatorEditEngineConfigurationEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'transactions.editengine.config';

  private $targetEngine;

  public function setTargetEngine(PhabricatorEditEngine $target_engine) {
    $this->targetEngine = $target_engine;
    return $this;
  }

  public function getTargetEngine() {
    return $this->targetEngine;
  }

  public function getEngineName() {
    return pht('Edit Configurations');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

  protected function newEditableObject() {
    return PhabricatorEditEngineConfiguration::initializeNewConfiguration(
      $this->getViewer(),
      $this->getTargetEngine());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorEditEngineConfigurationQuery());
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Form');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Form %d: %s', $object->getID(), $object->getDisplayName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Form %d', $object->getID());
  }

  protected function getObjectCreateShortText() {
    return pht('Create Form');
  }

  protected function getObjectViewURI($object) {
    $engine_key = $this->getTargetEngine()->getEngineKey();
    $id = $object->getID();
    return "/transactions/editengine/{$engine_key}/view/{$id}/";
  }

  protected function getObjectEditURI($object) {
    $engine_key = $this->getTargetEngine()->getEngineKey();
    $id = $object->getID();
    return "/transactions/editengine/{$engine_key}/edit/{$id}/";
  }

  protected function getObjectCreateCancelURI($object) {
    $engine_key = $this->getTargetEngine()->getEngineKey();
    return "/transactions/editengine/{$engine_key}/";
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the form.'))
        ->setTransactionType(
          PhabricatorEditEngineConfigurationTransaction::TYPE_NAME)
        ->setValue($object->getName()),
    );
  }

}
