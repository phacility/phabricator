<?php

final class PholioMockNameHeraldField
  extends PholioMockHeraldField {

  const FIELDCONST = 'pholio.mock.name';

  public function getHeraldFieldName() {
    return pht('Name');
  }

  public function getHeraldFieldValue($object) {
    return $object->getName();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
