<?php

final class PhabricatorProjectPictureProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.picture';

  public function getMenuItemTypeName() {
    return pht('Project Picture');
  }

  private function getDefaultName() {
    return pht('Project Picture');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getDefaultName();
  }

  public function getMenuItemTypeIcon() {
    return 'fa-image';
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

    $project = $config->getProfileObject();
    $picture = $project->getProfileImageURI();

    $item = $this->newItemView()
      ->setDisabled($project->isArchived());

    $item->newProfileImage($picture);

    return array(
      $item,
    );
  }

}
