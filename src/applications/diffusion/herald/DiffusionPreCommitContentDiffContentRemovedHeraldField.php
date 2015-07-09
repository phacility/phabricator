<?php

final class DiffusionPreCommitContentDiffContentRemovedHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.diff.old';

  public function getHeraldFieldName() {
    return pht('Removed diff content');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getDiffContent('-');
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_MAP;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
