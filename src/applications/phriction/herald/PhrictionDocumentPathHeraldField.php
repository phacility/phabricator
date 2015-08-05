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

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
