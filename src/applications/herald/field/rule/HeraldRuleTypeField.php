<?php

final class HeraldRuleTypeField
  extends HeraldRuleField {

  const FIELDCONST = 'rule-type';

  public function getHeraldFieldName() {
    return pht('Rule type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getRuleType();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new HeraldRuleTypeDatasource();
  }

  protected function getDatasourceValueMap() {
    return HeraldRuleTypeConfig::getRuleTypeMap();
  }

}
