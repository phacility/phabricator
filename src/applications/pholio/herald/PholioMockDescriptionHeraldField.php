<?php

final class PholioMockDescriptionHeraldField
  extends PholioMockHeraldField {

  const FIELDCONST = 'pholio.mock.description';

  public function getHeraldFieldName() {
    return pht('Description');
  }

  public function getHeraldFieldValue($object) {
    return $object->getDescription();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
