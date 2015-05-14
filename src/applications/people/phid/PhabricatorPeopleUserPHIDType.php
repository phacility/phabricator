<?php

final class PhabricatorPeopleUserPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'USER';

  public function getTypeName() {
    return pht('User');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getTypeIcon() {
    return 'fa-user bluegrey';
  }

  public function newObject() {
    return new PhabricatorUser();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPeopleQuery())
      ->withPHIDs($phids)
      ->needProfileImage(true)
      ->needStatus(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $user = $objects[$phid];
      $realname = $user->getRealName();

      $handle->setName($user->getUsername());
      $handle->setURI('/p/'.$user->getUsername().'/');
      $handle->setFullName($user->getFullName());
      $handle->setImageURI($user->getProfileImageURI());

      $availability = null;
      if (!$user->isUserActivated()) {
        $availability = PhabricatorObjectHandle::AVAILABILITY_DISABLED;
      } else {
        if ($user->hasStatus()) {
          // NOTE: This first call returns an event; then we get the event
          // status.
          $status = $user->getStatus()->getStatus();
          switch ($status) {
            case PhabricatorCalendarEvent::STATUS_AWAY:
              $availability = PhabricatorObjectHandle::AVAILABILITY_NONE;
              break;
            case PhabricatorCalendarEvent::STATUS_SPORADIC:
              $availability = PhabricatorObjectHandle::AVAILABILITY_PARTIAL;
              break;
          }
        }
      }

      if ($availability) {
        $handle->setAvailability($availability);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^@.+/', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = substr($name, 1);
      $id = phutil_utf8_strtolower($id);
      $id_map[$id][] = $name;
    }

    $objects = id(new PhabricatorPeopleQuery())
      ->setViewer($query->getViewer())
      ->withUsernames(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      $user_key = $object->getUsername();
      $user_key = phutil_utf8_strtolower($user_key);
      foreach (idx($id_map, $user_key, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
