<?php

final class PhabricatorPeopleFeedController
  extends PhabricatorPeopleController {

  private $username;

  public function shouldRequireAdmin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
  }

  public function processRequest() {
    require_celerity_resource('phabricator-profile-css');
    $viewer = $this->getRequest()->getUser();
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($this->username))
      ->needProfileImage(true)
      ->executeOne();

    if (!$user) {
      return new Aphront404Response();
    }

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit(100);
    $query->setViewer($viewer);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $builder->setShowHovercards(true);
    $builder->setNoDataString(
      pht(
        'To begin on such a grand journey, requires but just a single step.'));
    $view = $builder->buildView();

    $feed = phutil_tag_div(
      'phabricator-project-feed',
      $view->render());
    $name = $user->getUsername();

    $nav = $this->buildIconNavView($user);
    $nav->selectFilter("{$name}/feed/");
    $nav->appendChild($feed);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Feed'),
      ));
  }
}
