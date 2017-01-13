<?php

final class PhabricatorHomeProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  protected function getItemURI($path) {
    $object = $this->getProfileObject();
    $custom = $this->getCustomPHID();

    if ($custom) {
      return "/home/menu/personal/item/{$path}";
    } else {
      return "/home/menu/global/item/{$path}";
    }
  }

  protected function getBuiltinProfileItems($object) {
    $viewer = $this->getViewer();
    $items = array();
    $custom_phid = $this->getCustomPHID();

    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withUnlisted(false)
      ->withLaunchable(true)
      ->execute();

    foreach ($applications as $application) {
      if (!$application->isPinnedByDefault($viewer)) {
        continue;
      }

      $properties = array(
        'name' => $application->getName(),
        'application' => $application->getPHID(),
      );

      $items[] = $this->newItem()
        ->setBuiltinKey($application->getPHID())
        ->setMenuItemKey(PhabricatorApplicationProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperties($properties);
    }

    // Single Manage Item, switches URI based on admin/user
    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorHomeConstants::ITEM_MANAGE)
      ->setMenuItemKey(
        PhabricatorHomeManageProfileMenuItem::MENUITEMKEY);

    return $items;
  }

}
