<?php

final class PhabricatorLinkProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'link';

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $icon = $config->getPanelProperty('icon');
    $name = $config->getPanelProperty('name');
    $href = $config->getPanelProperty('href');

    $item = id(new PHUIListItemView())
      ->setRenderNameAsTooltip(true)
      ->setType(PHUIListItemView::TYPE_ICON_NAV)
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
