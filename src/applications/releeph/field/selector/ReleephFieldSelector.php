<?php

abstract class ReleephFieldSelector extends Phobject {

  final public function __construct() {
    // <empty>
  }

  abstract public function getFieldSpecifications();

  public function sortFieldsForCommitMessage(array $fields) {
    assert_instances_of($fields, 'ReleephFieldSpecification');
    return $fields;
  }

  protected static function selectFields(array $fields, array $classes) {
    assert_instances_of($fields, 'ReleephFieldSpecification');

    $map = array();
    foreach ($fields as $field) {
      $map[get_class($field)] = $field;
    }

    $result = array();
    foreach ($classes as $class) {
      $field = idx($map, $class);
      if (!$field) {
        throw new Exception(
          pht(
            "Tried to select a in instance of '%s' but that field ".
            "is not configured for this project!",
            $class));
      }

      if (idx($result, $class)) {
        throw new Exception(
          pht(
            "You have asked to select the field '%s' more than once!",
            $class));
      }

      $result[$class] = $field;
    }

    return $result;
  }

}
