<?php

final class PhabricatorPeoplePHIDTypeUser extends PhabricatorPHIDType {

  const TYPECONST = 'USER';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Phabricator User');
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
      $handle->setName($user->getUsername());
      $handle->setURI('/p/'.$user->getUsername().'/');
      $handle->setFullName(
        $user->getUsername().' ('.$user->getRealName().')');
      $handle->setImageURI($user->loadProfileImageURI());
      $handle->setDisabled($user->getIsDisabled());
      if ($user->hasStatus()) {
        $status = $user->getStatus();
        $handle->setStatus($status->getTextStatus());
        $handle->setTitle($status->getTerseSummary($query->getViewer()));
      }
    }

  }

}
