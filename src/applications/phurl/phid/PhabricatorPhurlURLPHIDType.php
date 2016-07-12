<?php

final class PhabricatorPhurlURLPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PHRL';

  public function getTypeName() {
    return pht('URL');
  }

  public function newObject() {
    return new PhabricatorPhurlURL();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPhurlURLQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $url = $objects[$phid];

      $id = $url->getID();
      $name = $url->getName();
      $full_name = $url->getMonogram().' '.$name;

      $handle
        ->setName($name)
        ->setFullName($full_name)
        ->setURI($url->getURI());
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^U[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorPhurlURLQuery())
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
