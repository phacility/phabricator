<?php

final class DifferentialRevisionPackageOwnerHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.package.owners';

  public function getHeraldFieldName() {
    return pht('Affected package owners');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAffectedPackages();
    if (!$packages) {
      return array();
    }

    $owners = PhabricatorOwnersOwner::loadAllForPackages($packages);
    return mpull($owners, 'getUserPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectOrUserDatasource();
  }

}
