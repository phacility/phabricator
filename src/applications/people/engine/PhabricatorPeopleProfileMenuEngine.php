<?php

final class PhabricatorPeopleProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  const ITEM_PROFILE = 'people.profile';
  const ITEM_MANAGE = 'people.manage';
  const ITEM_PICTURE = 'people.picture';

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

    $have_maniphest = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);
    if ($have_maniphest) {
      $uri = urisprintf(
        '/maniphest/?statuses=open()&assigned=%s#R',
        $object->getPHID());

      $items[] = $this->newItem()
        ->setBuiltinKey('tasks')
        ->setMenuItemKey(PhabricatorLinkProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperty('icon', 'maniphest')
        ->setMenuItemProperty('name', pht('Open Tasks'))
        ->setMenuItemProperty('uri', $uri);
    }

    $have_differential = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);
    if ($have_differential) {
      $uri = urisprintf(
        '/differential/?authors=%s#R',
        $object->getPHID());

      $items[] = $this->newItem()
        ->setBuiltinKey('revisions')
        ->setMenuItemKey(PhabricatorLinkProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperty('icon', 'differential')
        ->setMenuItemProperty('name', pht('Revisions'))
        ->setMenuItemProperty('uri', $uri);
    }

    $have_diffusion = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
    if ($have_diffusion) {
      $uri = urisprintf(
        '/diffusion/commit/?authors=%s#R',
        $object->getPHID());

      $items[] = $this->newItem()
        ->setBuiltinKey('commits')
        ->setMenuItemKey(PhabricatorLinkProfileMenuItem::MENUITEMKEY)
        ->setMenuItemProperty('icon', 'diffusion')
        ->setMenuItemProperty('name', pht('Commits'))
        ->setMenuItemProperty('uri', $uri);
    }

    $items[] = $this->newItem()
      ->setBuiltinKey(self::ITEM_MANAGE)
      ->setMenuItemKey(PhabricatorPeopleManageProfileMenuItem::MENUITEMKEY);

    return $items;
  }

}
