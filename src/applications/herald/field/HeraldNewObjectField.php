<?php

final class HeraldNewObjectField extends HeraldField {

  const FIELDCONST = 'new-object';

  public function getHeraldFieldName() {
    return pht('Is newly created');
  }

  public function getFieldGroupKey() {
    return HeraldEditFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return !$this->getAdapter()->isSingleEventAdapter();
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getIsNewObject();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_BOOL;
  }

}
