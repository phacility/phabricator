<?php

final class PhabricatorOwnersPackageEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'owners.package';

  public function getEngineName() {
    return pht('Owners Packages');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  protected function newEditableObject() {
    return PhabricatorOwnersPackage::initializeNewPackage($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorOwnersPackageQuery())
      ->needOwners(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Package');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Package %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Package %d', $object->getID());
  }

  protected function getObjectCreateShortText() {
    return pht('Create Package');
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/owners/package/{$id}/";
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the package.'))
        ->setTransactionType(PhabricatorOwnersPackageTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('owners')
        ->setLabel(pht('Owners'))
        ->setDescription(pht('Users and projects which own the package.'))
        ->setTransactionType(PhabricatorOwnersPackageTransaction::TYPE_OWNERS)
        ->setDatasource(new PhabricatorProjectOrUserDatasource())
        ->setValue($object->getOwnerPHIDs()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setDescription(pht('Archive or enable the package.'))
        ->setTransactionType(PhabricatorOwnersPackageTransaction::TYPE_STATUS)
        ->setValue($object->getStatus())
        ->setOptions($object->getStatusNameMap()),
      id(new PhabricatorSelectEditField())
        ->setKey('auditing')
        ->setLabel(pht('Auditing'))
        ->setDescription(
          pht(
            'Automatically trigger audits for commits affecting files in '.
            'this package.'))
        ->setTransactionType(PhabricatorOwnersPackageTransaction::TYPE_AUDITING)
        ->setValue($object->getAuditingEnabled())
        ->setOptions(
          array(
            '' => pht('Disabled'),
            '1' => pht('Enabled'),
          )),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Human-readable description of the package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION)
        ->setValue($object->getDescription()),
    );
  }

}
