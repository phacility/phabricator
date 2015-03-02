<?php

final class PhabricatorFeedBuilder {

  private $stories;
  private $framed;
  private $hovercards = false;
  private $noDataString;

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

  public function setShowHovercards($hover) {
    $this->hovercards = $hover;
    return $this;
  }

  public function setNoDataString($string) {
    $this->noDataString = $string;
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
      $story->setHovercard($this->hovercards);

      $date = ucfirst(phabricator_relative_date($story->getEpoch(), $user));

      if ($date !== $last_date) {
        if ($last_date !== null) {
          $null_view->appendChild(
            phutil_tag_div('phabricator-feed-story-date-separator'));
        }
        $last_date = $date;
        $header = new PHUIActionHeaderView();
        $header->setHeaderTitle($date);

        $null_view->appendChild($header);
      }

      try {
        $view = $story->renderView();
        $view->setUser($user);
        $view = $view->render();
      } catch (Exception $ex) {
        // If rendering failed for any reason, don't fail the entire feed,
        // just this one story.
        $view = id(new PHUIFeedStoryView())
          ->setUser($user)
          ->setChronologicalKey($story->getChronologicalKey())
          ->setEpoch($story->getEpoch())
          ->setTitle(
            pht('Feed Story Failed to Render (%s)', get_class($story)))
          ->appendChild(pht('%s: %s', get_class($ex), $ex->getMessage()));
      }

      $null_view->appendChild($view);
    }

    if (empty($stories)) {
      $nodatastring = pht('No Stories.');
      if ($this->noDataString) {
        $nodatastring = $this->noDataString;
      }

      $view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($nodatastring);
      $null_view->appendChild($view);
    }



    return id(new AphrontNullView())
      ->appendChild($null_view->render());
  }

}
