<?php

final class DiffusionCommitPackageAuditHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.package.audit';

  public function getHeraldFieldName() {
    return pht('Affected packages that need audit');
  }

  public function getHeraldFieldValue($object) {
    $packages = $this->getAdapter()->loadAuditNeededPackages();
    if (!$packages) {
      return array();
    }

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
