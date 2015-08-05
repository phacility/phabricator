<?php

final class DiffusionPreCommitContentMergeHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.merge';

  public function getHeraldFieldName() {
    return pht('Is merge commit');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getIsMergeCommit();
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_BOOL;
  }

}
