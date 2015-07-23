<?php

final class DifferentialDiffContentHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.content';

  public function getHeraldFieldName() {
    return pht('Changed file content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadContentDictionary();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
