<?php

final class PhabricatorCalendarExternalInviteePHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'CXNV';

  public function getTypeName() {
    return pht('External Invitee');
  }

  public function newObject() {
    return new PhabricatorCalendarExternalInvitee();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorCalendarExternalInviteeQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $invitee = $objects[$phid];

      $name = $invitee->getName();
      $handle->setName($name);
    }
  }
}
