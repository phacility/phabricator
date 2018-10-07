<?php

final class DifferentialRevisionTestPlanHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.test-plan';

  public function getHeraldFieldName() {
    return pht('Revision test plan');
  }

  public function getHeraldFieldValue($object) {
    return $object->getTestPlan();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
