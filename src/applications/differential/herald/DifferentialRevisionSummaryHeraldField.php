<?php

final class DifferentialRevisionSummaryHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.summary';

  public function getHeraldFieldName() {
    return pht('Revision summary');
  }

  public function getHeraldFieldValue($object) {
    // NOTE: For historical reasons, this field includes the test plan. We
    // could maybe try to fix this some day, but it probably aligns reasonably
    // well with user expectation without harming anything.
    return $object->getSummary()."\n\n".$object->getTestPlan();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
