<?php

abstract class DifferentialFieldSelector {

  final public function __construct() {
    // <empty>
  }

  final public static function newSelector() {
    return PhabricatorEnv::newObjectFromConfig('differential.field-selector');
  }

  abstract public function getFieldSpecifications();

  public function sortFieldsForRevisionList(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');
    return $fields;
  }

  public function sortFieldsForMail(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');
    return $fields;
  }

}
