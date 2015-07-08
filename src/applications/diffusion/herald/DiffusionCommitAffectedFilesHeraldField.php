<?php

final class DiffusionCommitAffectedFilesHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.affected';

  public function getHeraldFieldName() {
    return pht('Affected files');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAffectedPaths();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_TEXT_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TEXT;
  }

}
