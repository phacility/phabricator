<?php

final class DiffusionCommitDiffContentAddedHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.diff.new';

  public function getHeraldFieldName() {
    return pht('Diff content added');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadDiffContent('+');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
