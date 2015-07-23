<?php

final class DifferentialDiffContentRemovedHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.old';

  public function getHeraldFieldName() {
    return pht('Removed file content');
  }

  public function getFieldGroupKey() {
    return DifferentialChangeHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadRemovedContentDictionary();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_MAP;
  }

}
