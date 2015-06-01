<?php

final class PhabricatorSpacesNamespacePHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'SPCE';

  public function getTypeName() {
    return pht('Space');
  }

  public function newObject() {
    return new PhabricatorSpacesNamespace();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorSpacesNamespaceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $namespace = $objects[$phid];
      $handle->setName($namespace->getNamespaceName());
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^S[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorSpacesNamespaceQuery())
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
