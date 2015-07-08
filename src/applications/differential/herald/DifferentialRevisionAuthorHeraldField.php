<?php

final class DifferentialRevisionAuthorHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.author';

  public function getHeraldFieldName() {
    return pht('Author');
  }

  public function getHeraldFieldValue($object) {
    return $object->getAuthorPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
