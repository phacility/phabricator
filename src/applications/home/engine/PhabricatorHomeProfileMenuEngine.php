<?php

final class PhabricatorHomeProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  public function getItemURI($path) {
    return "/home/menu/{$path}";
  }

  protected function buildItemViewContent(
    PhabricatorProfileMenuItemConfiguration $item) {
    $viewer = $this->getViewer();

    // Add content to the document so that you can drag-and-drop files onto
    // the home page or any home dashboard to upload them.

    $upload = id(new PhabricatorGlobalUploadTargetView())
      ->setUser($viewer);

    $content = parent::buildItemViewContent($item);

    return array(
      $content,
      $upload,
    );
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

    // Default Home Dashboard
    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorHomeConstants::ITEM_HOME)
      ->setMenuItemKey(PhabricatorHomeProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorHomeConstants::ITEM_APPS_LABEL)
      ->setMenuItemKey(PhabricatorLabelProfileMenuItem::MENUITEMKEY)
      ->setMenuItemProperties(array('name' => pht('Applications')));

    foreach ($applications as $application) {
      if (!$application->isPinnedByDefault($viewer)) {
        continue;
      }

      $properties = array(
        'name' => '',
        'application' => $application->getPHID(),
      );

      $items[] = $this->newItem()
        ->setBuiltinKey($application->getPHID())
        ->setMenuItemKey(PhabricatorApplicationProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperties($properties);
    }

    // Hotlink to More Applications Launcher...
    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorHomeConstants::ITEM_LAUNCHER)
      ->setMenuItemKey(PhabricatorHomeLauncherProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newManageItem();

    return $items;
  }

}
