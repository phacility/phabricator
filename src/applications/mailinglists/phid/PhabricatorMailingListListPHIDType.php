<?php

final class PhabricatorMailingListListPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'MLST';

  public function getTypeName() {
    return pht('Mailing List');
  }

  public function getTypeIcon() {
    return 'fa-envelope-o';
  }

  public function newObject() {
    return new PhabricatorMetaMTAMailingList();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMailingListQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $list = $objects[$phid];

      $handle->setName($list->getName());
      $handle->setURI($list->getURI());
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^.+@.+/', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      // Maybe normalize these some day?
      $id = $name;
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorMailingListQuery())
      ->setViewer($query->getViewer())
      ->withEmails(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      $email = $object->getEmail();
      foreach (idx($id_map, $email, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
