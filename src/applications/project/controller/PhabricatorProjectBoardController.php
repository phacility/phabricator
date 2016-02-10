<?php

abstract class PhabricatorProjectBoardController
  extends PhabricatorProjectController {

  protected function getProfileMenu() {
    $menu = parent::getProfileMenu();

    $menu->selectFilter(PhabricatorProject::PANEL_WORKBOARD);
    $menu->addClass('project-board-nav');

    return $menu;
  }
}
