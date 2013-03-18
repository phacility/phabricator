<?php

final class ReleephProjectViewController extends ReleephController {

  public function processRequest() {
    // Load all branches
    $releeph_project = $this->getReleephProject();
    $releeph_branches = id(new ReleephBranch())
      ->loadAllWhere('releephProjectID = %d',
                     $releeph_project->getID());

    $path = $this->getRequest()->getRequestURI()->getPath();
    $is_open_branches = strpos($path, 'closedbranches/') === false;

    $view = id(new ReleephProjectView())
      ->setShowOpenBranches($is_open_branches)
      ->setUser($this->getRequest()->getUser())
      ->setReleephProject($releeph_project)
      ->setBranches($releeph_branches);

    $crumbs = $this->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($releeph_project->getName())
          ->setHref($releeph_project->getURI()));

    if ($releeph_project->getIsActive()) {
      $crumbs->addAction(
        id(new PhabricatorMenuItemView())
          ->setHref($releeph_project->getURI('cutbranch'))
          ->setName('Cut New Branch')
          ->setIcon('create'));
    }

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $view,
      ),
      array(
        'title' => $releeph_project->getName().' Releeph Project'
      ));
  }

}
