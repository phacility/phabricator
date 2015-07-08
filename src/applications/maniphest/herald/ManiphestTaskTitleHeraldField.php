<?php

final class ManiphestTaskTitleHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.title';

  public function getHeraldFieldName() {
    return pht('Title');
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
