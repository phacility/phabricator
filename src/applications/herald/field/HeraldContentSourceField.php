<?php

final class HeraldContentSourceField extends HeraldField {

  const FIELDCONST = 'contentsource';

  public function getHeraldFieldName() {
    return pht('Content source');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getContentSource()->getSource();
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_IS,
      HeraldAdapter::CONDITION_IS_NOT,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_CONTENT_SOURCE;
  }

  public function supportsObject($object) {
    return true;
  }

}
