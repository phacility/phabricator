<?php

final class PhabricatorCalendarEventPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CEVT';

  public function getTypeName() {
    return pht('Event');
  }

  public function newObject() {
    return new PhabricatorCalendarEvent();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorCalendarEventQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $event = $objects[$phid];

      $monogram = $event->getMonogram();
      $name = $event->getName();
      $uri = $event->getURI();

      $handle
        ->setName($name)
        ->setFullName(pht('%s: %s', $monogram, $name))
        ->setURI($uri);

      if ($event->getIsCancelled()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^E[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorCalendarEventQuery())
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
