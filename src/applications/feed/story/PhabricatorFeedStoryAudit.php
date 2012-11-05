<?php

final class PhabricatorFeedStoryAudit extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getStoryData()->getValue('commitPHID');
  }

  public function renderView() {
    $author_phid = $this->getAuthorPHID();
    $commit_phid = $this->getPrimaryObjectPHID();

    $view = new PhabricatorFeedStoryView();

    $action = $this->getValue('action');
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $view->setTitle(
      $this->linkTo($author_phid).
      " {$verb} commit ".
      $this->linkTo($commit_phid).
      ".");

    $view->setEpoch($this->getEpoch());

    $comments = $this->getValue('content');
    if ($comments) {
      $full_size = true;
    } else {
      $full_size = false;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($author_phid)->getImageURI());
      $content = $this->renderSummary($this->getValue('content'));
      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
