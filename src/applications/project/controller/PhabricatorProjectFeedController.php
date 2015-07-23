<?php

final class PhabricatorProjectFeedController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->needSlugs(true);
    $id = $request->getURIData('id');
    $slug = $request->getURIData('slug');
    if ($slug) {
      $query->withSlugs(array($slug));
    } else {
      $query->withIDs(array($id));
    }
    $project = $query->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    if ($slug && $slug != $project->getPrimarySlug()) {
      return id(new AphrontRedirectResponse())
        ->setURI('/tag/'.$project->getPrimarySlug().'/');
    }

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $project->getPHID(),
      ));
    $query->setLimit(50);
    $query->setViewer($request->getUser());
    $stories = $query->execute();
    $feed = $this->renderStories($stories);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Project Activity'))
      ->appendChild($feed);

    $nav = $this->buildIconNavView($project);
    $nav->selectFilter("feed/{$id}/");
    $nav->appendChild($box);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $project->getName(),
      ));
  }

  private function renderFeedPage(PhabricatorProject $project) {

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(array($project->getPHID()));
    $query->setViewer($this->getRequest()->getUser());
    $query->setLimit(100);
    $stories = $query->execute();

    if (!$stories) {
      return pht('There are no stories about this project.');
    }

    return $this->renderStories($stories);
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
