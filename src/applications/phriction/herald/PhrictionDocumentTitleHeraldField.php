<?php

final class PhrictionDocumentTitleHeraldField
  extends PhrictionDocumentHeraldField {

  const FIELDCONST = 'phriction.document.title';

  public function getHeraldFieldName() {
    return pht('Title');
  }

  public function getHeraldFieldValue($object) {
    return $object->getContent()->getTitle();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
