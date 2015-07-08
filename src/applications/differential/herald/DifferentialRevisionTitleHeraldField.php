<?php

final class DifferentialRevisionTitleHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.title';

  public function getHeraldFieldName() {
    return pht('Revision title');
  }

  public function getHeraldFieldValue($object) {
    return $object->getTitle();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
