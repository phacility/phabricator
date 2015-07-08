<?php

final class DifferentialRevisionReviewersHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.reviewers';

  public function getHeraldFieldName() {
    return pht('Reviewers');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadReviewers();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_USER_OR_PROJECT;
    }
  }

}
