<?php

final class DifferentialDiffAffectedFilesHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.affected';

  public function getHeraldFieldName() {
    return pht('Affected files');
  }

  public function getFieldGroupKey() {
    return DifferentialChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAffectedPaths();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_LIST;
  }

}
