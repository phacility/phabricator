<?php

final class DifferentialRevisionPackageOwnerHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.package.owners';

  public function getHeraldFieldName() {
    return pht('Affected package owners');
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAffectedPackages();
    if (!$packages) {
      return array();
    }

    $owners = PhabricatorOwnersOwner::loadAllForPackages($packages);
    return mpull($owners, 'getUserPHID');
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_USER_OR_PROJECT;
    }
  }

}
