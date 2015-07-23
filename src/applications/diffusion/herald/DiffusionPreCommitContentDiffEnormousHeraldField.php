<?php

final class DiffusionPreCommitContentDiffEnormousHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.diff.enormous';

  public function getHeraldFieldName() {
    return pht('Diff is enormous');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->isDiffEnormous();
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_BOOL;
  }

}
