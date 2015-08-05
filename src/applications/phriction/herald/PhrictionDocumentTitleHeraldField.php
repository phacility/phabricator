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

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
