<?php

final class PhabricatorProjectDetailsProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.details';

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $project = $config->getProfileObject();

    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $href = "/project/profile/{$id}/";

    $item = id(new PHUIListItemView())
      ->setRenderNameAsTooltip(true)
      ->setType(PHUIListItemView::TYPE_ICON_NAV)
      ->setHref($href)
      ->setName($name)
      ->setProfileImage($picture);

    return array(
      $item,
    );
  }

}
