<?php

final class HarbormasterBuildablePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBB';

  public function getTypeName() {
    return pht('Buildable');
  }

  public function newObject() {
    return new HarbormasterBuildable();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildableQuery())
      ->withPHIDs($phids)
      ->needBuildableHandles(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $buildable = $objects[$phid];

      $id = $buildable->getID();
      $target = $buildable->getBuildableHandle()->getFullName();

      $handle->setURI("/B{$id}");
      $handle->setName("B{$id}");
      $handle->setFullName("B{$id}: ".$target);
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^B\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new HarbormasterBuildableQuery())
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
