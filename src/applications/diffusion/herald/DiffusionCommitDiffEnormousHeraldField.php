<?php

final class DiffusionCommitDiffEnormousHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.enormous';

  public function getHeraldFieldName() {
    return pht('Change is enormous');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->isDiffEnormous();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_BOOL;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_NONE;
  }

}
