<?php

final class PhrictionDocumentPathHeraldField
  extends PhrictionDocumentHeraldField {

  const FIELDCONST = 'path';

  public function getHeraldFieldName() {
    return pht('Path');
  }

  public function getHeraldFieldValue($object) {
    return $object->getcontent()->getSlug();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
