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

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
