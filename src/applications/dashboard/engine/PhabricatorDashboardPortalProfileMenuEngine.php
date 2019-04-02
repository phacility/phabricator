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

    $items[] = $this->newDividerItem('tail');

    $items[] = $this->newManageItem();

    $items[] = $this->newItem()
      ->setMenuItemKey(PhabricatorDashboardPortalMenuItem::MENUITEMKEY)
      ->setBuiltinKey('manage')
      ->setIsTailItem(true);

    return $items;
  }

  protected function newNoMenuItemsView(array $items) {
    $object = $this->getProfileObject();
    $builtins = $this->getBuiltinProfileItems($object);

    if (count($items) <= count($builtins)) {
      return $this->newEmptyView(
        pht('New Portal'),
        pht('Use "Edit Menu" to add menu items to this portal.'));
    } else {
      return $this->newEmptyValue(
        pht('No Portal Content'),
        pht(
          'None of the visible menu items in this portal can render any '.
          'content.'));
    }
  }

}
