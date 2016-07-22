<?php

final class PhabricatorPackagesPublisherEditEngine
  extends PhabricatorPackagesEditEngine {

  const ENGINECONST = 'packages.publisher';

  public function getEngineName() {
    return pht('Package Publishers');
  }

  public function getSummaryHeader() {
    return pht('Edit Package Publisher Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Packages publishers.');
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorPackagesPublisher::initializeNewPublisher($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorPackagesPublisherQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Publisher');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Publisher');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Publisher: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Publisher');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Publisher');
  }

  protected function getObjectName() {
    return pht('Publisher');
  }

  protected function getEditorURI() {
    return '/packages/publisher/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/packages/publisher/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhabricatorPackagesCreatePublisherCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    $fields = array();

    $fields[] = id(new PhabricatorTextEditField())
      ->setKey('name')
      ->setLabel(pht('Name'))
      ->setDescription(pht('Name of the publisher.'))
      ->setTransactionType(
        PhabricatorPackagesPublisherNameTransaction::TRANSACTIONTYPE)
      ->setIsRequired(true)
      ->setValue($object->getName());

    if ($this->getIsCreate()) {
      $fields[] = id(new PhabricatorTextEditField())
        ->setKey('publisherKey')
        ->setLabel(pht('Publisher Key'))
        ->setDescription(pht('Unique key to identify the publisher.'))
        ->setTransactionType(
          PhabricatorPackagesPublisherKeyTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getPublisherKey());
    }

    return $fields;
  }

}
