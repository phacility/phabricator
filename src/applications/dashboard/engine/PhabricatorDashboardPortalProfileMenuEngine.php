<?php

final class PhabricatorDashboardPortalProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  protected function isMenuEnginePersonalizable() {
    return false;
  }

  public function getItemURI($path) {
    $portal = $this->getProfileObject();

    return $portal->getURI().$path;
  }

  protected function getBuiltinProfileItems($object) {
    $items = array();

    $items[] = $this->newManageItem();

    $items[] = $this->newItem()
      ->setMenuItemKey(PhabricatorDashboardPortalMenuItem::MENUITEMKEY)
      ->setBuiltinKey('manage');

    return $items;
  }

  protected function newNoMenuItemsView() {
    return $this->newEmptyView(
      pht('New Portal'),
      pht('Use "Edit Menu" to add menu items to this portal.'));
  }

}
