<?php

final class HeraldAnotherRuleField extends HeraldField {

  const FIELDCONST = 'rule';

  public function getHeraldFieldName() {
    return pht('Another Herald rule');
  }

  public function getFieldGroupKey() {
    return HeraldBasicFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return true;
  }

  public function getHeraldFieldValue($object) {
    return null;
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_RULE,
      HeraldAdapter::CONDITION_NOT_RULE,
    );
  }

  public function getHeraldFieldValueType($condition) {
    // NOTE: This is a bit magical because we don't currently have a reasonable
    // way to populate it from here.
    return id(new HeraldSelectFieldValue())
      ->setKey(self::FIELDCONST)
      ->setOptions(array());
  }

  public function renderConditionValue(
    PhabricatorUser $viewer,
    $condition,
    $value) {

    $value = (array)$value;

    return $viewer->renderHandleList($value);
  }

}
