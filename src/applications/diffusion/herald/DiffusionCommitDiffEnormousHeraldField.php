<?php

final class DiffusionCommitDiffEnormousHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.enormous';

  public function getHeraldFieldName() {
    return pht('Change is enormous');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->isDiffEnormous();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_BOOL;
  }

}
