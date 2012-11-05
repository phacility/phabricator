<?php

final class PhabricatorFeedPublicStreamController
  extends PhabricatorFeedController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    if (!PhabricatorEnv::getEnvConfig('feed.public')) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $query = new PhabricatorFeedQuery();
    $query->setViewer($viewer);
    $query->setLimit(100);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder
      ->setFramed(true)
      ->setUser($viewer);

    $view = $builder->buildView();

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title'   => 'Public Feed',
        'public'  => true,
      ));
  }
}
