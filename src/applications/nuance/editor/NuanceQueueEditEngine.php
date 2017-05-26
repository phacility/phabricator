<?php

final class NuanceQueueEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'nuance.queue';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Nuance Queues');
  }

  public function getSummaryHeader() {
    return pht('Edit Nuance Queue Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Nuance queues.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  protected function newEditableObject() {
    return NuanceQueue::initializeNewQueue();
  }

  protected function newObjectQuery() {
    return new NuanceQueueQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Queue');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Queue');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Queue: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Queue');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Queue');
  }

  protected function getObjectName() {
    return pht('Queue');
  }

  protected function getEditorURI() {
    return '/nuance/queue/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/nuance/queue/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the queue.'))
        ->setTransactionType(NuanceQueueNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
    );
  }

}
