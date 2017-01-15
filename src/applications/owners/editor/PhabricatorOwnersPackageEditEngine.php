<?php

final class PhabricatorOwnersPackageEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'owners.package';

  public function getEngineName() {
    return pht('Owners Packages');
  }

  public function getSummaryHeader() {
    return pht('Configure Owners Package Forms');
  }

  public function getSummaryText() {
    return pht('Configure forms for creating and editing packages in Owners.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  protected function newEditableObject() {
    return PhabricatorOwnersPackage::initializeNewPackage($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorOwnersPackageQuery())
      ->needPaths(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Package');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Package: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Package %d', $object->getID());
  }

  protected function getObjectCreateShortText() {
    return pht('Create Package');
  }

  protected function getObjectName() {
    return pht('Package');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {

    $paths_help = pht(<<<EOTEXT
When updating the paths for a package, pass a list of dictionaries like
this as the `value` for the transaction:

```lang=json, name="Example Paths Value"
[
  {
    "repositoryPHID": "PHID-REPO-1234",
    "path": "/path/to/directory/",
    "excluded": false
  },
  {
    "repositoryPHID": "PHID-REPO-1234",
    "path": "/another/example/path/",
    "excluded": false
  }
]
```

This transaction will set the paths to the list you provide, overwriting any
previous paths.

Generally, you will call `owners.search` first to get a list of current paths
(which are provided in the same format), make changes, then update them by
applying a transaction of this type.
EOTEXT
      );

    $autoreview_map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    $autoreview_map = ipull($autoreview_map, 'name');

    $dominion_map = PhabricatorOwnersPackage::getDominionOptionsMap();
    $dominion_map = ipull($dominion_map, 'name');

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('owners')
        ->setLabel(pht('Owners'))
        ->setDescription(pht('Users and projects which own the package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageOwnersTransaction::TRANSACTIONTYPE)
        ->setDatasource(new PhabricatorProjectOrUserDatasource())
        ->setIsCopyable(true)
        ->setValue($object->getOwnerPHIDs()),
      id(new PhabricatorSelectEditField())
        ->setKey('dominion')
        ->setLabel(pht('Dominion'))
        ->setDescription(
          pht('Change package dominion rules.'))
        ->setTransactionType(
          PhabricatorOwnersPackageDominionTransaction::TRANSACTIONTYPE)
        ->setIsCopyable(true)
        ->setValue($object->getDominion())
        ->setOptions($dominion_map),
      id(new PhabricatorSelectEditField())
        ->setKey('autoReview')
        ->setLabel(pht('Auto Review'))
        ->setDescription(
          pht(
            'Automatically trigger reviews for commits affecting files in '.
            'this package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageAutoreviewTransaction::TRANSACTIONTYPE)
        ->setIsCopyable(true)
        ->setValue($object->getAutoReview())
        ->setOptions($autoreview_map),
      id(new PhabricatorSelectEditField())
        ->setKey('auditing')
        ->setLabel(pht('Auditing'))
        ->setDescription(
          pht(
            'Automatically trigger audits for commits affecting files in '.
            'this package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageAuditingTransaction::TRANSACTIONTYPE)
        ->setIsCopyable(true)
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
          PhabricatorOwnersPackageDescriptionTransaction::TRANSACTIONTYPE)
        ->setValue($object->getDescription()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setDescription(pht('Archive or enable the package.'))
        ->setTransactionType(
          PhabricatorOwnersPackageStatusTransaction::TRANSACTIONTYPE)
        ->setIsConduitOnly(true)
        ->setValue($object->getStatus())
        ->setOptions($object->getStatusNameMap()),
      id(new PhabricatorConduitEditField())
        ->setKey('paths.set')
        ->setLabel(pht('Paths'))
        ->setIsConduitOnly(true)
        ->setTransactionType(
          PhabricatorOwnersPackagePathsTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(
          pht('Overwrite existing package paths with new paths.'))
        ->setConduitTypeDescription(
          pht('List of dictionaries, each describing a path.'))
        ->setConduitDocumentation($paths_help),
    );
  }

}
