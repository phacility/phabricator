<?php

final class DifferentialRevisionStatusHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'revision.status';

  public function getHeraldFieldName() {
    return pht('Revision status');
  }

  public function getHeraldFieldValue($object) {
    return $object->getStatus();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new DifferentialRevisionStatusDatasource();
  }

  protected function getDatasourceValueMap() {
    $map = DifferentialRevisionStatus::getAll();
    return mpull($map, 'getDisplayName', 'getKey');
  }

}
