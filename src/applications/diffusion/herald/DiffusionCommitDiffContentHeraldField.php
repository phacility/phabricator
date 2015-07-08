<?php

final class DiffusionCommitDiffContentHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.diff';

  public function getHeraldFieldName() {
    return pht('Diff content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadDiffContent('*');
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
