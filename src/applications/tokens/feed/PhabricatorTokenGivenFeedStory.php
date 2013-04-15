<?php

final class PhabricatorTokenGivenFeedStory
  extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('objectPHID');
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $phids[] = $this->getValue('objectPHID');
    $phids[] = $this->getValue('authorPHID');
    return $phids;
  }

  public function renderView() {
    $view = new PHUIFeedStoryView();
    $view->setAppIcon('token-dark');
    $view->setViewed($this->getHasViewed());
    $author_phid = $this->getValue('authorPHID');

    $href = $this->getHandle($this->getPrimaryObjectPHID())->getURI();
    $view->setHref($href);

    $title = pht(
      '%s awarded %s a token.',
      $this->linkTo($this->getValue('authorPHID')),
      $this->linkTo($this->getValue('objectPHID')));

    $view->setTitle($title);
    $view->setImage($this->getHandle($author_phid)->getImageURI());

    return $view;
  }

  public function renderText() {
    // TODO: This is grotesque; the feed notification handler relies on it.
    return strip_tags($this->renderView()->render());
  }

}
