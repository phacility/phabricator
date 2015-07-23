<?php

final class DiffusionCommitAffectedFilesHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.affected';

  public function getHeraldFieldName() {
    return pht('Affected files');
  }

  public function getFieldGroupKey() {
    return DiffusionChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAffectedPaths();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_LIST;
  }

}
