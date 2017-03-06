<?php

final class PhabricatorPeopleProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  const ITEM_PROFILE = 'people.profile';
  const ITEM_MANAGE = 'people.manage';
  const ITEM_PICTURE = 'people.picture';
  const ITEM_BADGES = 'people.badges';
  const ITEM_TASKS = 'people.tasks';
  const ITEM_COMMITS = 'people.commits';

  protected function isMenuEngineConfigurable() {
    return false;
  }

  public function getItemURI($path) {
    $user = $this->getProfileObject();
    $username = $user->getUsername();
    $username = phutil_escape_uri($username);
    return "/p/{$username}/item/{$path}";
  }

  protected function getBuiltinProfileItems($object) {
    $viewer = $this->getViewer();

    $items = array();

    $items[] = $this->newItem()
      ->setBuiltinKey(self::ITEM_PICTURE)
      ->setMenuItemKey(PhabricatorPeoplePictureProfileMenuItem::MENUITEMKEY);

    $items[] = $this->newItem()
      ->setBuiltinKey(self::ITEM_PROFILE)
      ->setMenuItemKey(PhabricatorPeopleDetailsProfileMenuItem::MENUITEMKEY);

    $have_badges = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorBadgesApplication',
      $viewer);
    if ($have_badges) {
      $items[] = $this->newItem()
        ->setBuiltinKey(self::ITEM_BADGES)
        ->setMenuItemKey(PhabricatorPeopleBadgesProfileMenuItem::MENUITEMKEY);
    }

    $have_maniphest = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);
    if ($have_maniphest) {
      $items[] = $this->newItem()
        ->setBuiltinKey(self::ITEM_TASKS)
        ->setMenuItemKey(PhabricatorPeopleTasksProfileMenuItem::MENUITEMKEY);
    }

    $have_diffusion = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
    if ($have_diffusion) {
      $items[] = $this->newItem()
        ->setBuiltinKey(self::ITEM_COMMITS)
        ->setMenuItemKey(PhabricatorPeopleCommitsProfileMenuItem::MENUITEMKEY);
    }

    $items[] = $this->newItem()
      ->setBuiltinKey(self::ITEM_MANAGE)
      ->setMenuItemKey(PhabricatorPeopleManageProfileMenuItem::MENUITEMKEY);

    return $items;
  }

}
