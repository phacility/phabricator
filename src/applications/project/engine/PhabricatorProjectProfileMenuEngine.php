<?php

final class PhabricatorProjectProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
    return true;
  }

  protected function isMenuEnginePersonalizable() {
    return false;
  }

  public function getItemURI($path) {
    $project = $this->getProfileObject();
    $id = $project->getID();
    return "/project/{$id}/item/{$path}";
  }

  protected function getBuiltinProfileItems($object) {
    $items = array();

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_PICTURE)
      ->setMenuItemKey(PhabricatorProjectPictureProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_PROFILE)
      ->setMenuItemKey(PhabricatorProjectDetailsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_POINTS)
      ->setMenuItemKey(PhabricatorProjectPointsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_WORKBOARD)
      ->setMenuItemKey(PhabricatorProjectWorkboardProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_MEMBERS)
      ->setMenuItemKey(PhabricatorProjectMembersProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_SUBPROJECTS)
      ->setMenuItemKey(
        PhabricatorProjectSubprojectsProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(PhabricatorProject::ITEM_MANAGE)
      ->setMenuItemKey(PhabricatorProjectManageProfileMenuItem::MENUITEMKEY);

    return $items;
  }

}
