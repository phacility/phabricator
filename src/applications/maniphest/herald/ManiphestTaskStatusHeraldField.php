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

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_TASK_STATUS;
  }

  public function renderConditionValue(
    PhabricatorUser $viewer,
    $value) {

    $status_map = ManiphestTaskStatus::getTaskStatusMap();

    $value = (array)$value;
    foreach ($value as $index => $val) {
      $name = idx($status_map, $val);
      if ($name !== null) {
        $value[$index] = $name;
      }
    }

    return implode(', ', $value);
  }

  public function getEditorValue(
    PhabricatorUser $viewer,
    $value) {

    $status_map = ManiphestTaskStatus::getTaskStatusMap();

    $value_map = array();
    foreach ($value as $status) {
      $value_map[$status] = idx($status_map, $status, $status);
    }

    return $value_map;
  }

}
