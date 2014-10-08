<?php

final class PhabricatorPastePastePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PSTE';

  public function getTypeName() {
    return pht('Paste');
  }

  public function newObject() {
    return new PhabricatorPaste();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPasteQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $paste = $objects[$phid];

      $id = $paste->getID();
      $name = $paste->getFullName();

      $handle->setName("P{$id}");
      $handle->setFullName($name);
      $handle->setURI("/P{$id}");
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^P\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorPasteQuery())
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
