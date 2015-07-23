<?php

final class DiffusionPreCommitContentDiffEnormousHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.diff.enormous';

  public function getHeraldFieldName() {
    return pht('Diff is enormous');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->isDiffEnormous();
  }

  protected function getHeraldFieldStandardConditions() {
    return HeraldField::STANDARD_BOOL;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_NONE;
  }

}
