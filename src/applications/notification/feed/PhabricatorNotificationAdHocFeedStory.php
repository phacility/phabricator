<?php

final class PhabricatorNotificationAdHocFeedStory extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getAuthorPHID();
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();

    $view = $this->newStoryView();

    $view->setTitle($data->getValue('title'));
    $view->setImage($this->getHandle($author_phid)->getImageURI());

    return $view;
  }

  public function renderText() {
    $data = $this->getStoryData();
    return $data->getValue('title');
  }

}
