<?php

final class DifferentialRevisionAffectedFilesHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.diff.affected';

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
