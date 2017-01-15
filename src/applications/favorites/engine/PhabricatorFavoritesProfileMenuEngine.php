<?php

final class PhabricatorFavoritesProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  protected function getItemURI($path) {
    $object = $this->getProfileObject();
    $custom = $this->getCustomPHID();

    if ($custom) {
      return "/favorites/personal/item/{$path}";
    } else {
      return "/favorites/global/item/{$path}";
    }
  }

  protected function getBuiltinProfileItems($object) {
    $items = array();
    $custom_phid = $this->getCustomPHID();

    // Built-in Global Defaults
    if (!$custom_phid) {
      $create_task = array(
        'name' => null,
        'formKey' =>
          id(new ManiphestEditEngine())->getProfileMenuItemDefault(),
      );

      $create_project = array(
        'name' => null,
        'formKey' =>
          id(new PhabricatorProjectEditEngine())->getProfileMenuItemDefault(),
      );

      $create_repository = array(
        'name' => null,
        'formKey' =>
          id(new DiffusionRepositoryEditEngine())->getProfileMenuItemDefault(),
      );

      $items[] = $this->newItem()
        ->setBuiltinKey(PhabricatorFavoritesConstants::ITEM_TASK)
        ->setMenuItemKey(PhabricatorEditEngineProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperties($create_task);

      $items[] = $this->newItem()
        ->setBuiltinKey(PhabricatorFavoritesConstants::ITEM_PROJECT)
        ->setMenuItemKey(PhabricatorEditEngineProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperties($create_project);

      $items[] = $this->newItem()
        ->setBuiltinKey(PhabricatorFavoritesConstants::ITEM_REPOSITORY)
        ->setMenuItemKey(PhabricatorEditEngineProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperties($create_repository);
    }

    // Single Manage Item, switches URI based on admin/user
    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorFavoritesConstants::ITEM_MANAGE)
      ->setMenuItemKey(
        PhabricatorFavoritesManageProfileMenuItem::MENUITEMKEY);

    return $items;
  }

}
