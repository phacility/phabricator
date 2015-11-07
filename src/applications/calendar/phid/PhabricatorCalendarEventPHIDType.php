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

      $id = $event->getID();
      $name = $event->getName();
      $is_cancelled = $event->getIsCancelled();

      $handle
        ->setName($name)
        ->setFullName(pht('E%d: %s', $id, $name))
        ->setURI('/E'.$id);

      if ($is_cancelled) {
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
