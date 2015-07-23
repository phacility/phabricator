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

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
