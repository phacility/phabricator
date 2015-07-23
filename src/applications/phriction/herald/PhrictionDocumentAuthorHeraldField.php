<?php

final class PhrictionDocumentAuthorHeraldField
  extends PhrictionDocumentHeraldField {

  const FIELDCONST = 'phriction.document.author';

  public function getHeraldFieldName() {
    return pht('Author');
  }

  public function getHeraldFieldValue($object) {
    return $object->getContent()->getAuthorPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
