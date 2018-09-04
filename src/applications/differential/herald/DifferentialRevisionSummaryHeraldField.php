<?php

final class DifferentialRevisionSummaryHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.summary';

  public function getHeraldFieldName() {
    return pht('Revision summary');
  }

  public function getHeraldFieldValue($object) {
    return $object->getSummary();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
