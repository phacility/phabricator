<?php

final class PhabricatorPeopleMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'people';

  public function buildMainMenus() {
    $viewer = $this->getViewer();

    // TODO: This should get cached.

    $profile = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->needProfileImage(true)
      ->withPHIDs(array($viewer->getPHID()))
      ->executeOne();
    $image = $profile->getProfileImageURI();

    $bar_item = id(new PHUIListItemView())
      ->setName($viewer->getUsername())
      ->setHref('/p/'.$viewer->getUsername().'/')
      ->addClass('core-menu-item')
      ->setAural(pht('Profile'));

    $classes = array(
      'phabricator-core-menu-icon',
      'phabricator-core-menu-profile-image',
    );

    $bar_item->appendChild(
      phutil_tag(
        'span',
        array(
          'class' => implode(' ', $classes),
          'style' => 'background-image: url('.$image.')',
        ),
        ''));

    $profile_menu = id(new PHUIMainMenuView())
      ->setOrder(100)
      ->setMenuBarItem($bar_item);

    return array(
      $profile_menu,
    );
  }

}
