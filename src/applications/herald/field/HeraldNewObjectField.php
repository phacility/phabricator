<?php

final class HeraldNewObjectField extends HeraldField {

  const FIELDCONST = 'new-object';

  public function getHeraldFieldName() {
    return pht('Is newly created');
  }

  public function supportsObject($object) {
    return !$this->getAdapter()->isSingleEventAdapter();
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getIsNewObject();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_BOOL;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_NONE;
  }

}
