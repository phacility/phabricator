<?php

final class PhabricatorRoleProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  protected function isMenuEnginePersonalizable() {
    return false;
  }

  public function getItemURI($path) {
    $role = $this->getProfileObject();
    $id = $role->getID();
    return "/role/{$id}/item/{$path}";
  }

  protected function getBuiltinProfileItems($object) {
    $items = array();

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_PICTURE)
      ->setMenuItemKey(PhabricatorRolePictureProfileMenuItem::MENUITEMKEY)
      ->setIsHeadItem(true);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_PROFILE)
      ->setMenuItemKey(PhabricatorRoleDetailsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_POINTS)
      ->setMenuItemKey(PhabricatorRolePointsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_WORKBOARD)
      ->setMenuItemKey(PhabricatorRoleWorkboardProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_REPORTS)
      ->setMenuItemKey(PhabricatorRoleReportsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_MEMBERS)
      ->setMenuItemKey(PhabricatorRoleMembersProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_SUBROLES)
      ->setMenuItemKey(
        PhabricatorRoleSubrolesProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorRole::ITEM_MANAGE)
      ->setMenuItemKey(PhabricatorRoleManageProfileMenuItem::MENUITEMKEY)
      ->setIsTailItem(true);

    return $items;
  }

}
