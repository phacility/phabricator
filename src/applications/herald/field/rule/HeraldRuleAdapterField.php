<?php

final class HeraldRuleAdapterField
  extends HeraldRuleField {

  const FIELDCONST = 'adapter';

  public function getHeraldFieldName() {
    return pht('Content type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getContentType();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new HeraldAdapterDatasource();
  }

  protected function getDatasourceValueMap() {
    $adapters = HeraldAdapter::getAllAdapters();
    return mpull($adapters, 'getAdapterContentName', 'getAdapterContentType');
  }

}
