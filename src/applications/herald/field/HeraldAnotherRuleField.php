<?php

final class HeraldAnotherRuleField extends HeraldField {

  const FIELDCONST = 'rule';

  public function getHeraldFieldName() {
    return pht('Another Herald rule');
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
    return HeraldAdapter::VALUE_RULE;
  }


}
