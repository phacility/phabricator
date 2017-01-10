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

    $custom = $this->getCustomPHID();

    if ($custom) {
      $items[] = $this->newItem()
        ->setBuiltinKey(PhabricatorFavoritesConstants::ITEM_MANAGE)
        ->setMenuItemKey(
          PhabricatorFavoritesManageProfileMenuItem::MENUITEMKEY);
    }

    return $items;
  }

}
