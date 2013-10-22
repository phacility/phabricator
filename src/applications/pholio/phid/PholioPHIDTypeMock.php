<?php

final class PholioPHIDTypeMock extends PhabricatorPHIDType {

  const TYPECONST = 'MOCK';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Mock');
  }

  public function newObject() {
    return new PholioMock();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PholioMockQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $mock = $objects[$phid];

      $id = $mock->getID();
      $name = $mock->getName();

      $handle->setURI("/M{$id}");
      $handle->setName("M{$id}");
      $handle->setFullName("M{$id}: {$name}");
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^M\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PholioMockQuery())
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
