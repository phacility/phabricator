<?php

final class DiffusionPreCommitContentMessageHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.message';

  public function getHeraldFieldName() {
    return pht('Commit message');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getCommitRef()->getMessage();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
