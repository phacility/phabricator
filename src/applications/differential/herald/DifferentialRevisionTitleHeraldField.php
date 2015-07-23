<?php

final class DifferentialRevisionTitleHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.title';

  public function getHeraldFieldName() {
    return pht('Revision title');
  }

  public function getHeraldFieldValue($object) {
    return $object->getTitle();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
