<?php

final class PhabricatorProjectWorkboardProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.workboard';

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {
    $viewer = $this->getViewer();

    // Workboards are only available if Maniphest is installed.
    $class = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return array();
    }

    $project = $config->getProfileObject();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $icon = 'fa-columns';
    } else {
      $icon = 'fa-columns grey';
    }

    $id = $project->getID();
    $href = "/project/board/{$id}/";
    $name = pht('Workboard');

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
