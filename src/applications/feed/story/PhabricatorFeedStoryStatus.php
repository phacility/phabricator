<?php

final class PhabricatorFeedStoryStatus extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getAuthorPHID();
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();

    $view = new PHUIFeedStoryView();
    $view->setAppIcon('calendar-dark');

    $view->setTitle($this->linkTo($author_phid));
    $view->setEpoch($data->getEpoch());
    $view->setImage($this->getHandle($author_phid)->getImageURI());

    $content = $this->renderSummary($data->getValue('content'), $len = null);
    $view->appendChild($content);

    return $view;
  }

  public function renderText() {
    $author_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $author_name = $author_handle->getLinkName();
    $author_uri = PhabricatorEnv::getURI($author_handle->getURI());

    $text = pht('% supdated their status %s', $author_name, $author_uri);

    return $text;
  }

}
