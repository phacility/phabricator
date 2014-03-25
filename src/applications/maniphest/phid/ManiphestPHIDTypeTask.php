<?php

final class ManiphestPHIDTypeTask extends PhabricatorPHIDType {

  const TYPECONST = 'TASK';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Task');
  }

  public function newObject() {
    return new ManiphestTask();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ManiphestTaskQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $task = $objects[$phid];
      $id = $task->getID();
      $title = $task->getTitle();

      $handle->setName("T{$id}");
      $handle->setFullName("T{$id}: {$title}");
      $handle->setURI("/T{$id}");

      if ($task->isClosed()) {
        $handle->setStatus(PhabricatorObjectHandleStatus::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^T\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new ManiphestTaskQuery())
      ->setViewer($query->getViewer())
      ->withIDs(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      foreach (idx($id_map, $id, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
