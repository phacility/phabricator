<?php

final class PhabricatorSlowvotePollPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'POLL';

  public function getTypeName() {
    return pht('Slowvote Poll');
  }

  public function newObject() {
    return new PhabricatorSlowvotePoll();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorSlowvoteApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorSlowvoteQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $poll = $objects[$phid];

      $handle->setName('V'.$poll->getID());
      $handle->setFullName('V'.$poll->getID().': '.$poll->getQuestion());
      $handle->setURI('/V'.$poll->getID());
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^V\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorSlowvoteQuery())
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
