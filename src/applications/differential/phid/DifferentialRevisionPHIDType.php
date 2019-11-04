<?php

final class DifferentialRevisionPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DREV';

  public function getTypeName() {
    return pht('Differential Revision');
  }

  public function newObject() {
    return new DifferentialRevision();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDifferentialApplication';
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
      $monogram = $revision->getMonogram();
      $uri = $revision->getURI();

      $handle
        ->setName($monogram)
        ->setURI($uri)
        ->setFullName("{$monogram}: {$title}");

      if ($revision->isClosed()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
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
