<?php

final class DifferentialRevisionPackageHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.package';

  public function getHeraldFieldName() {
    return pht('Affected packages');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAffectedPackages();
    return mpull($packages, 'getPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorOwnersPackageDatasource();
  }

}
