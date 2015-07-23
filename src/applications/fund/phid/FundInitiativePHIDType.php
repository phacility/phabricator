<?php

final class FundInitiativePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'FITV';

  public function getTypeName() {
    return pht('Fund Initiative');
  }

  public function newObject() {
    return new FundInitiative();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new FundInitiativeQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $initiative = $objects[$phid];

      $id = $initiative->getID();
      $monogram = $initiative->getMonogram();
      $name = $initiative->getName();

      if ($initiative->isClosed()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }

      $handle->setName($name);
      $handle->setFullName("{$monogram} {$name}");
      $handle->setURI("/I{$id}");
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^I\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new FundInitiativeQuery())
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
