<?php

final class PhabricatorBadgesPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'BDGE';

  public function getTypeName() {
    return pht('Badge');
  }

  public function newObject() {
    return new PhabricatorBadgesBadge();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorBadgesQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $badge = $objects[$phid];

      $id = $badge->getID();
      $name = $badge->getName();

      if ($badge->isArchived()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }

      $handle->setName($name);
      $handle->setURI("/badges/view/{$id}/");
    }
  }

}
