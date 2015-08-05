<?php

final class ManiphestTaskPriorityHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'taskpriority';

  public function getHeraldFieldName() {
    return pht('Priority');
  }

  public function getHeraldFieldValue($object) {
    return $object->getPriority();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new ManiphestTaskPriorityDatasource();
  }

  protected function getDatasourceValueMap() {
    return ManiphestTaskPriority::getTaskPriorityMap();
  }

}
