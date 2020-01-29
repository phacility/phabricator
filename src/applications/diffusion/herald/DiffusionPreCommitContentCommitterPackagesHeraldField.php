<?php

final class DiffusionPreCommitContentCommitterPackagesHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.committer.packages';

  public function getHeraldFieldName() {
    return pht("Committer's packages");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $committer_phid = $adapter->getCommitterPHID();
    if (!$committer_phid) {
      return array();
    }

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withAuthorityPHIDs(array($committer_phid))
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
