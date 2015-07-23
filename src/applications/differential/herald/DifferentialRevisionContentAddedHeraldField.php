<?php

final class DifferentialRevisionContentAddedHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.diff.new';

  public function getHeraldFieldName() {
    return pht('Added file content');
  }

  public function getFieldGroupKey() {
    return DifferentialChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadAddedContentDictionary();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
