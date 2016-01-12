<?php

abstract class PhabricatorProjectBoardController
  extends PhabricatorProjectController {

  public function buildIconNavView(PhabricatorProject $project) {
    $id = $project->getID();
    $nav = parent::buildIconNavView($project);
    $nav->selectFilter(PhabricatorProject::PANEL_WORKBOARD);
    $nav->addClass('project-board-nav');
    return $nav;
  }
}
