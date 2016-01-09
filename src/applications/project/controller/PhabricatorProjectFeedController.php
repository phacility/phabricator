<?php

final class PhabricatorProjectFeedController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $id = $project->getID();

    $stories = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->setFilterPHIDs(
        array(
          $project->getPHID(),
        ))
      ->setLimit(50)
      ->execute();

    $feed = $this->renderStories($stories);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Project Activity'))
      ->appendChild($feed);

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("feed/{$id}/");

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Feed'));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Feed')))
      ->appendChild($box);
  }

  private function renderStories(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($this->getRequest()->getUser());
    $builder->setShowHovercards(true);
    $view = $builder->buildView();

    return phutil_tag_div(
      'profile-feed',
      $view->render());
  }

}
