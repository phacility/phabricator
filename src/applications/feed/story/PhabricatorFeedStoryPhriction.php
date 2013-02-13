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

    $view->setTitle(hsprintf(
      '%s %s the document %s.',
      $this->linkTo($author_phid),
      $verb,
      $this->linkTo($document_phid)));
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

  public function renderText() {
    $author_name = $this->getHandle($this->getAuthorPHID())->getLinkName();

    $document_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $document_title = $document_handle->getLinkName();
    $document_uri = PhabricatorEnv::getURI($document_handle->getURI());

    $action = $this->getValue('action');
    $verb = PhrictionActionConstants::getActionPastTenseVerb($action);

    $text = "{$author_name} {$verb} the document".
            "{$document_title} {$document_uri}";

    return $text;
  }

}
