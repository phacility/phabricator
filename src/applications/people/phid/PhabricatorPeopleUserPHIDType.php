<?php

final class PhabricatorPeopleUserPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'USER';

  public function getTypeName() {
    return pht('User');
  }

  public function getTypeIcon() {
    return 'fa-user bluegrey';
  }

  public function newObject() {
    return new PhabricatorUser();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPeopleQuery())
      ->withPHIDs($phids)
      ->needProfile(true)
      ->needProfileImage(true)
      ->needAvailability(true);
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

      if ($user->getIsMailingList()) {
        $handle->setIcon('fa-envelope-o');
        $handle->setSubtitle(pht('Mailing List'));
      } else {
        $profile = $user->getUserProfile();
        $icon_key = $profile->getIcon();
        $icon_icon = PhabricatorPeopleIconSet::getIconIcon($icon_key);
        $subtitle = $profile->getDisplayTitle();

        $handle
          ->setIcon($icon_icon)
          ->setSubtitle($subtitle)
          ->setTokenIcon('fa-user');
      }

      $availability = null;
      if (!$user->isResponsive()) {
        $availability = PhabricatorObjectHandle::AVAILABILITY_NOEMAIL;
      } else {
        $until = $user->getAwayUntil();
        if ($until) {
          $away = PhabricatorCalendarEventInvitee::AVAILABILITY_AWAY;
          if ($user->getDisplayAvailability() == $away) {
            $availability = PhabricatorObjectHandle::AVAILABILITY_NONE;
          } else {
            $availability = PhabricatorObjectHandle::AVAILABILITY_PARTIAL;
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
