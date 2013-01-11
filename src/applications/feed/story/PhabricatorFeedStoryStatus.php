<?php

final class PhabricatorFeedStoryStatus extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getAuthorPHID();
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();

    $view = new PhabricatorFeedStoryView();

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

    $text = "{$author_name} updated their status {$author_url}";

    return $text;
  }

}
