<?php

final class DifferentialRevisionContentHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.diff.content';

  public function getHeraldFieldName() {
    return pht('Changed file content');
  }

  public function getFieldGroupKey() {
    return DifferentialChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadContentDictionary();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
