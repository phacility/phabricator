<?php

final class PhabricatorCalendarEventPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CEVT';

  public function getTypeName() {
    return pht('Event');
  }

  public function newObject() {
    return new PhabricatorCalendarEvent();
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

      $handle->setName(pht('Event %d', $id));
    }
  }

}
