<?php

final class DiffusionPreCommitContentDiffContentRemovedHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.diff.old';

  public function getHeraldFieldName() {
    return pht('Removed diff content');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getDiffContent('-');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
