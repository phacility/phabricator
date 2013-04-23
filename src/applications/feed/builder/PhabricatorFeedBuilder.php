<?php

final class PhabricatorFeedBuilder {

  private $stories;
  private $framed;

  public function __construct(array $stories) {
    assert_instances_of($stories, 'PhabricatorFeedStory');
    $this->stories = $stories;
  }

  public function setFramed($framed) {
    $this->framed = $framed;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function buildView() {
    if (!$this->user) {
      throw new Exception('Call setUser() before buildView()!');
    }

    $user = $this->user;
    $stories = $this->stories;

    $null_view = new AphrontNullView();

    require_celerity_resource('phabricator-feed-css');

    $last_date = null;
    foreach ($stories as $story) {
      $story->setFramed($this->framed);

      $date = ucfirst(phabricator_relative_date($story->getEpoch(), $user));

      if ($date !== $last_date) {
        if ($last_date !== null) {
          $null_view->appendChild(hsprintf(
            '<div class="phabricator-feed-story-date-separator"></div>'));
        }
        $last_date = $date;
        $header = new PhabricatorActionHeaderView();
        $header->setHeaderTitle($date);

        $null_view->appendChild($header);
      }

      $view = $story->renderView();
      $view->setUser($user);

      $null_view->appendChild($view);
    }

    return id(new AphrontNullView())
      ->appendChild($null_view->render());
  }

}
