<?php

final class DiffusionCommitAuthorPackagesHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.author.packages';

  public function getHeraldFieldName() {
    return pht("Author's packages");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $author_phid = $adapter->getAuthorPHID();
    if (!$author_phid) {
      return array();
    }

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withAuthorityPHIDs(array($author_phid))
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
