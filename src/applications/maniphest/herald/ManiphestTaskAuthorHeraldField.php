<?php

final class ManiphestTaskAuthorHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.author';

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
