<?php

final class DiffusionPreCommitContentBranchesHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.branches';

  public function getHeraldFieldName() {
    return pht('Branches');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getBranches();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
