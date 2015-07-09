<?php

final class DifferentialDiffAffectedFilesHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.affected';

  public function getHeraldFieldName() {
    return pht('Affected files');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAffectedPaths();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_TEXT;
    }
  }

}
