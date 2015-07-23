<?php

final class DiffusionCommitDiffContentAddedHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.diff.new';

  public function getHeraldFieldName() {
    return pht('Diff content added');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadDiffContent('+');
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
