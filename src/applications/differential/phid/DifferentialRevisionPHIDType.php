<?php

final class DifferentialRevisionPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DREV';

  public function getTypeName() {
    return pht('Differential Revision');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function newObject() {
    return new DifferentialRevision();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DifferentialRevisionQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $revision = $objects[$phid];

      $title = $revision->getTitle();
      $id = $revision->getID();
      $status = $revision->getStatus();

      $handle->setName("D{$id}");
      $handle->setURI("/D{$id}");
      $handle->setFullName("D{$id}: {$title}");

      if ($revision->isClosed()) {
        $handle->setStatus(PhabricatorObjectHandleStatus::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^D[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new DifferentialRevisionQuery())
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
