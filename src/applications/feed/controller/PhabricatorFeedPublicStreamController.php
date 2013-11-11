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
    $viewer = PhabricatorUser::getOmnipotentUser();

    $query = new PhabricatorFeedQuery();
    $query->setViewer($viewer);
    $query->setLimit(100);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder
      ->setFramed(true)
      ->setUser($viewer);

    $view = phutil_tag_div(
      'phabricator-public-feed-frame',
      $builder->buildView());

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => pht('Public Feed'),
        'public' => true,
      ));
  }
}
