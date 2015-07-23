<?php

final class DiffusionPreCommitContentDiffContentAddedHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.diff.new';

  public function getHeraldFieldName() {
    return pht('Added diff content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getDiffContent('+');
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
