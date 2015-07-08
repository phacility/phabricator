<?php

final class DiffusionCommitAuthorHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.author';

  public function getHeraldFieldName() {
    return pht('Author');
  }

  public function getHeraldFieldValue($object) {
    return $object->getCommitData()->getCommitDetail('authorPHID');
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
        return HeraldAdapter::VALUE_USER;
    }
  }

}
