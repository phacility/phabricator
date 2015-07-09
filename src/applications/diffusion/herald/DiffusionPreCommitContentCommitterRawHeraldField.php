<?php

final class DiffusionPreCommitContentCommitterRawHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.committer.raw';

  public function getHeraldFieldName() {
    return pht('Raw Committer');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getCommitterRaw();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
