<?php

final class ManiphestTaskStatusHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'taskstatus';

  public function getHeraldFieldName() {
    return pht('Status');
  }

  public function getHeraldFieldValue($object) {
    return $object->getStatus();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new ManiphestTaskStatusDatasource();
  }

  protected function getDatasourceValueMap() {
    return ManiphestTaskStatus::getTaskStatusMap();
  }

}
