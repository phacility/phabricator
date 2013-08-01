<?php

final class PhabricatorFeedDetailController extends PhabricatorFeedController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $story = id(new PhabricatorFeedQuery())
      ->setViewer($user)
      ->withChronologicalKeys(array($this->id))
      ->executeOne();
    if (!$story) {
      return new Aphront404Response();
    }

    $feed = array($story);
    $builder = new PhabricatorFeedBuilder($feed);
    $builder->setUser($user);
    $feed_view = $builder->buildView();

    $title = pht('Story');

    $feed_view = hsprintf(
      '<div class="phabricator-feed-frame">%s</div>',
      $feed_view);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title));


    return $this->buildApplicationPage(
      array(
        $crumbs,
        $feed_view,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}
