<?php

final class DifferentialRevisionPackageHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.package';

  public function getHeraldFieldName() {
    return pht('Affected packages');
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAffectedPackages();
    return mpull($packages, 'getPHID');
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
        return HeraldAdapter::VALUE_OWNERS_PACKAGE;
    }
  }

}
