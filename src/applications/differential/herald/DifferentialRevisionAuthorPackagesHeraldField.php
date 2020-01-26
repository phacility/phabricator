<?php

final class DifferentialRevisionAuthorPackagesHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.author.packages';

  public function getHeraldFieldName() {
    return pht("Author's packages");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withAuthorityPHIDs(array($object->getAuthorPHID()))
      ->execute();

    return mpull($packages, 'getPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorOwnersPackageDatasource();
  }

}
