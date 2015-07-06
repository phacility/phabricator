<?php

final class ManiphestTaskAssigneeHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.assignee';

  public function getHeraldFieldName() {
    return pht('Assignee');
  }

  public function getHeraldFieldValue($object) {
    return $object->getOwnerPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID_NULLABLE;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
