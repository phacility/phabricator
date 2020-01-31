<?php

final class HeraldAlwaysField extends HeraldField {

  const FIELDCONST = 'always';

  public function getHeraldFieldName() {
    return pht('Always');
  }

  public function getFieldGroupKey() {
    return HeraldBasicFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    return true;
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_UNCONDITIONALLY,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return new HeraldBoolFieldValue();
  }

  public function supportsObject($object) {
    return true;
  }

}
