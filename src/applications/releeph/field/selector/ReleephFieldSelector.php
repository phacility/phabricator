<?php

/**
 * Control the rendering of ReleephRequestHeaderView, and the layout of the
 * ReleephRequest search dialog (in ReleephBranchViewController.)
 */
abstract class ReleephFieldSelector {

  final public function __construct() {
    // <empty>
  }

  abstract public function getFieldSpecifications();

  abstract public function arrangeFieldsForHeaderView(array $fields);

  abstract public function arrangeFieldsForSelectForm(array $fields);

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
          "Tried to select a in instance of '{$class}' but that field ".
          "is not configured for this project!");
      }

      if (idx($result, $class)) {
        throw new Exception(
          "You have asked to select the field '{$class}' ".
          "more than once!");
      }

      $result[$class] = $field;
    }

    return $result;
  }

}
