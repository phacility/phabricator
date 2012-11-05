<?php

final class PhabricatorFeedStoryPhriction extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('phid');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();
    $document_phid = $data->getValue('phid');

    $view = new PhabricatorFeedStoryView();

    $action = $data->getValue('action');
    $verb = PhrictionActionConstants::getActionPastTenseVerb($action);

    $view->setTitle(
      $this->linkTo($author_phid).
      " {$verb} the document ".
      $this->linkTo($document_phid).'.');
    $view->setEpoch($data->getEpoch());

    $action = $data->getValue('action');
    switch ($action) {
      case PhrictionActionConstants::ACTION_CREATE:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($author_phid)->getImageURI());
      $content = $this->renderSummary($data->getValue('content'));
      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
