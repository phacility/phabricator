<?php

final class HarbormasterBuildablePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBB';

  public function getTypeName() {
    return pht('Buildable');
  }

  public function newObject() {
    return new HarbormasterBuildable();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildableQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();

    $target_phids = array();
    foreach ($objects as $phid => $object) {
      $target_phids[] = $object->getBuildablePHID();
    }
    $target_handles = $viewer->loadHandles($target_phids);

    foreach ($handles as $phid => $handle) {
      $buildable = $objects[$phid];

      $id = $buildable->getID();
      $buildable_phid = $buildable->getBuildablePHID();

      $target = $target_handles[$buildable_phid];
      $target_name = $target->getFullName();

      $uri = $buildable->getURI();
      $monogram = $buildable->getMonogram();

      $handle
        ->setURI($uri)
        ->setName($monogram)
        ->setFullName("{$monogram}: {$target_name}");
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
