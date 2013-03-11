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

  public function loadFields(ManiphestTask $task, PhabricatorUser $viewer) {
    $aux_fields = $this->getAuxiliaryFieldSpecifications();
    if (!$aux_fields) {
      return array();
    }

    $task->loadAndAttachAuxiliaryAttributes();

    foreach ($aux_fields as $aux) {
      $aux->setUser($viewer);
      $aux->setTask($task);

      // If we're creating a new task, we don't bother loading any stored data.
      // This allows any defaults configured by the Extensions object to
      // survive.
      if ($task->getID()) {
        $key = $aux->getAuxiliaryKey();
        $aux->setValueFromStorage($task->getAuxiliaryAttribute($key));
      }
    }

    return $aux_fields;
  }

}
