<?php

final class DifferentialDiffContentRemovedHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.old';

  public function getHeraldFieldName() {
    return pht('Removed file content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadRemovedContentDictionary();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
