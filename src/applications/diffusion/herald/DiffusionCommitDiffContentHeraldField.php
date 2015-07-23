<?php

final class DiffusionCommitDiffContentHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.diff';

  public function getHeraldFieldName() {
    return pht('Diff content');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadDiffContent('*');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
