<?php

final class DiffusionCommitMergeHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.merge';

  public function getHeraldFieldName() {
    return pht('Is merge commit');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadIsMergeCommit();
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_BOOL;
  }

}
