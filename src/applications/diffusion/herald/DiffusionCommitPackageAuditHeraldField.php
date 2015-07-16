<?php

final class DiffusionCommitPackageAuditHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.package.audit';

  public function getHeraldFieldName() {
    return pht('Affected packages that need audit');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAuditNeededPackages();
    if (!$packages) {
      return array();
    }

    return mpull($packages, 'getPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorOwnersPackageDatasource();
  }

}
