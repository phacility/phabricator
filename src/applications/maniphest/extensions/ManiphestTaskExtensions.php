<?php

/**
 * @group maniphest
 */
abstract class ManiphestTaskExtensions {

  final public function __construct() {
    // <empty>
  }

  abstract public function getAuxiliaryFieldSpecifications();


  final public static function newExtensions() {
    $key = 'maniphest.custom-task-extensions-class';
    return PhabricatorEnv::newObjectFromConfig($key);
  }

}
