<?php

final class PhabricatorPackagesVersionEditEngine
  extends PhabricatorPackagesEditEngine {

  const ENGINECONST = 'packages.version';

  public function getEngineName() {
    return pht('Package Versions');
  }

  public function getSummaryHeader() {
    return pht('Edit Package Version Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Packages versions.');
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorPackagesVersion::initializeNewVersion($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorPackagesVersionQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Version');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Version');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Version: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Version');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Version');
  }

  protected function getObjectName() {
    return pht('Version');
  }

  protected function getEditorURI() {
    return '/packages/version/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/packages/version/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    $fields = array();

    if ($this->getIsCreate()) {
      $fields[] = id(new PhabricatorDatasourceEditField())
        ->setKey('package')
        ->setAliases(array('packagePHID'))
        ->setLabel(pht('Package'))
        ->setDescription(pht('Package for this version.'))
        ->setTransactionType(
          PhabricatorPackagesVersionPackageTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setDatasource(new PhabricatorPackagesPackageDatasource())
        ->setSingleValue($object->getPackagePHID());

      $fields[] = id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the version.'))
        ->setTransactionType(
          PhabricatorPackagesVersionNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName());
    }

    return $fields;
  }

}
