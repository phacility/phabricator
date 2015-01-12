<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $id = null;
    if ($for_app) {
      $user = $this->getRequest()->getUser();
      $id = $this->getRequest()->getURIData('id');
      if ($id) {
        $nav->addFilter("profile/{$id}/", pht('Profile'));
        $nav->addFilter("board/{$id}/", pht('Workboard'));
        $nav->addFilter("members/{$id}/", pht('Members'));
        $nav->addFilter("feed/{$id}/", pht('Feed'));
        $nav->addFilter("edit/{$id}/", pht('Edit'));
      }
      $nav->addFilter('create', pht('Create Project'));
    }

    if (!$id) {
      id(new PhabricatorProjectSearchEngine())
        ->setViewer($user)
        ->addNavigationItems($nav->getMenu());
    }

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildIconNavView(PhabricatorProject $project) {
    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $nav = new AphrontSideNavFilterView();
    $nav->setIconNav(true);
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addIcon("profile/{$id}/", $name, null, $picture);
    $nav->addIcon("board/{$id}/", pht('Workboard'), 'fa-columns');
    $nav->addIcon("feed/{$id}/", pht('Feed'), 'fa-newspaper-o');
    $nav->addIcon("members/{$id}/", pht('Members'), 'fa-group');
    $nav->addIcon("edit/{$id}/", pht('Edit'), 'fa-pencil');

    return $nav;
  }

}
