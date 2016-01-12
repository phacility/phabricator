<?php

final class PhabricatorProjectMembersProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.members';

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $project = $config->getProfileObject();

    $id = $project->getID();

    $name = pht('Members');
    $icon = 'fa-group';
    $href = "/project/members/{$id}/";

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
