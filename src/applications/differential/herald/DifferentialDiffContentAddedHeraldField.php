<?php

final class DifferentialDiffContentAddedHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.new';

  public function getHeraldFieldName() {
    return pht('Added file content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAddedContentDictionary();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
