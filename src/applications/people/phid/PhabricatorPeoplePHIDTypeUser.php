<?php

final class PhabricatorPeoplePHIDTypeUser extends PhabricatorPHIDType {

  const TYPECONST = 'USER';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('User');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorApplicationPeople';
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
      $handle->setImageURI($user->loadProfileImageURI());
      $handle->setDisabled(!$user->isUserActivated());
      if ($user->hasStatus()) {
        $status = $user->getStatus();
        $handle->setStatus($status->getTextStatus());
        $handle->setTitle($status->getTerseSummary($query->getViewer()));
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
