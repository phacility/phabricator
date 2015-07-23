<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  private $project;

  protected function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  protected function getProject() {
    return $this->project;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  public function buildSideNavView($for_app = false) {
    $project = $this->getProject();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $viewer = $this->getViewer();

    $id = null;
    if ($for_app) {
      if ($project) {
        $id = $project->getID();
        $nav->addFilter("profile/{$id}/", pht('Profile'));
        $nav->addFilter("board/{$id}/", pht('Workboard'));
        $nav->addFilter("members/{$id}/", pht('Members'));
        $nav->addFilter("feed/{$id}/", pht('Feed'));
        $nav->addFilter("details/{$id}/", pht('Edit Details'));
      }
      $nav->addFilter('create', pht('Create Project'));
    }

    if (!$id) {
      id(new PhabricatorProjectSearchEngine())
        ->setViewer($viewer)
        ->addNavigationItems($nav->getMenu());
    }

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildIconNavView(PhabricatorProject $project) {
    $this->setProject($project);
    $viewer = $this->getViewer();
    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $board_icon = 'fa-columns';
    } else {
      $board_icon = 'fa-columns grey';
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setIconNav(true);
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addIcon("profile/{$id}/", $name, null, $picture);
    $nav->addIcon("board/{$id}/", pht('Workboard'), $board_icon);

    $class = 'PhabricatorManiphestApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $phid = $project->getPHID();
      $query_uri = urisprintf(
        '/maniphest/?statuses=open()&projects=%s#R',
        $phid);
      $nav->addIcon(null, pht('Open Tasks'), 'fa-anchor', null, $query_uri);
    }

    $nav->addIcon("feed/{$id}/", pht('Feed'), 'fa-newspaper-o');
    $nav->addIcon("members/{$id}/", pht('Members'), 'fa-group');
    $nav->addIcon("details/{$id}/", pht('Edit Details'), 'fa-pencil');

    return $nav;
  }

}
