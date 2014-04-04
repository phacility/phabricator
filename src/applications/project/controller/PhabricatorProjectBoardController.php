<?php

abstract class PhabricatorProjectBoardController
  extends PhabricatorProjectController {

  private $project;

  protected function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }
  protected function getProject() {
    return $this->project;
  }

  protected function buildApplicationCrumbs() {
    $project = $this->getProject();
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $project->getName(),
      $this->getApplicationURI('view/'.$project->getID().'/'));
    return $crumbs;
  }
}
