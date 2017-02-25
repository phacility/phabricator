<?php

final class PeopleHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'people';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('User Accounts');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorUser);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->needAvailability(true)
      ->needProfileImage(true)
      ->needProfile(true)
      ->execute();
    $users = mpull($users, null, 'getPHID');

    return array(
      'users' => $users,
    );
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {
    $viewer = $this->getViewer();

    $user = idx($data['users'], $object->getPHID());
    if (!$user) {
      return;
    }

    $user_card = id(new PhabricatorUserCardView())
      ->setProfile($user)
      ->setViewer($viewer);

    $hovercard->appendChild($user_card);

  }

}
