<?php

final class PhabricatorRolePictureProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.picture';

  public function getMenuItemTypeName() {
    return pht('Role Picture');
  }

  private function getDefaultName() {
    return pht('Role Picture');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-image';
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array();
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $role = $config->getProfileObject();
    $picture = $role->getProfileImageURI();

    $item = $this->newItemView()
      ->setDisabled($role->isArchived());

    $item->newProfileImage($picture);

    return array(
      $item,
    );
  }

}
