<?php

final class PhabricatorSettingsMainMenuBarExtension
  extends PhabricatorMainMenuBarExtension {

  const MAINMENUBARKEY = 'settings';

  public function buildMainMenus() {
    $controller = $this->getController();
    $is_selected = ($controller instanceof PhabricatorSettingsMainController);

    $bar_item = id(new PHUIListItemView())
      ->setName(pht('Settings'))
      ->setIcon('fa-wrench')
      ->addClass('core-menu-item')
      ->setSelected($is_selected)
      ->setHref('/settings/')
      ->setAural(pht('Settings'));

    $settings_menu = id(new PHUIMainMenuView())
      ->setMenuBarItem($bar_item)
      ->setOrder(400);

    return array(
      $settings_menu,
    );
  }

}
