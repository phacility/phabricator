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

    $project = $config->getProfileObject();
    require_celerity_resource('people-picture-menu-item-css');

    $picture = $project->getProfileImageURI();
    $href = $project->getProfileURI();

    $classes = array();
    $classes[] = 'people-menu-image';
    if ($project->isArchived()) {
      $classes[] = 'phui-image-disabled';
    }

    $photo = phutil_tag(
      'img',
      array(
        'src' => $picture,
        'class' => implode(' ', $classes),
      ));

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
