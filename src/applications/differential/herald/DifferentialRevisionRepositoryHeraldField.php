<?php

final class DifferentialRevisionRepositoryHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.repository';

  public function getHeraldFieldName() {
    return pht('Repository');
  }

  public function getHeraldFieldValue($object) {
    $repository = $this->getAdapter()->loadRepository();

    if (!$repository) {
      return null;
    }

    return $repository->getPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID_NULLABLE;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_REPOSITORY;
    }
  }

}
