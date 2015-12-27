<?php

final class PhabricatorProjectSubprojectsController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $id = $project->getID();

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("subprojects/{$id}/");

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Subprojects'));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Subprojects')));
  }

}
