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

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $user = $config->getProfileObject();
    require_celerity_resource('people-picture-menu-item-css');

    $picture = $user->getProfileImageURI();
    $name = $user->getUsername();
    $href = urisprintf(
      '/p/%s/',
      $user->getUsername());

    $photo = phutil_tag(
      'img',
      array(
        'src' => $picture,
        'class' => 'people-menu-image',
      ));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $this->getViewer(),
      $user,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $id = $user->getID();
      $href = "/people/picture/{$id}/";
    }

    $view = phutil_tag_div('people-menu-image-container', $photo);
    $view = phutil_tag(
      'a',
      array(
        'href' => $href,
      ),
      $view);

    $item = $this->newItem()
      ->appendChild($view);

    return array(
      $item,
    );
  }

}
