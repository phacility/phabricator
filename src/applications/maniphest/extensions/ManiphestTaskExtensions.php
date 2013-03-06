<?php

/**
 * @group maniphest
 */
abstract class ManiphestTaskExtensions {

  final public function __construct() {
    // <empty>
  }

  abstract public function getAuxiliaryFieldSpecifications();

  abstract public function getGroupedAuxiliaryFieldSpecifications(array $aux_fields);

  abstract public function renderGroupedFields(array $aux_groups, $task, $user, $userdata, $skip_empty, $skip_desc, $group_callback, $field_callback);

  final public static function newExtensions() {
    $key = 'maniphest.custom-task-extensions-class';
    return PhabricatorEnv::newObjectFromConfig($key);
  }

}
