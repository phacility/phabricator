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

  public function renderConditionValue(
    PhabricatorUser $viewer,
    $condition,
    $value) {

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    $value = (array)$value;
    foreach ($value as $index => $val) {
      $name = idx($priority_map, $val);
      if ($name !== null) {
        $value[$index] = $name;
      }
    }

    return implode(', ', $value);
  }

  public function getEditorValue(
    PhabricatorUser $viewer,
    $value) {

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    $value_map = array();
    foreach ($value as $priority) {
      $value_map[$priority] = idx($priority_map, $priority, $priority);
    }

    return $value_map;
  }

}
