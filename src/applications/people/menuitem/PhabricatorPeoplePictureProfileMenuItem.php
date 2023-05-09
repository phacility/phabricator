<?php

final class PhabricatorPeoplePictureProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'people.picture';

  public function getMenuItemTypeName() {
    return pht('User Picture');
  }

  private function getDefaultName() {
    return pht('User Picture');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getDefaultName();
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array();
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $user = $config->getProfileObject();

    $picture = $user->getProfileImageURI();
    $name = $user->getUsername();

    $item = $this->newItemView()
      ->setDisabled($user->getIsDisabled());

    $item->newProfileImage($picture);

    return array(
      $item,
    );
  }

}
