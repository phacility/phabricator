<?php

final class PhabricatorFeedStoryPhriction extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('phid');
  }

  public function getRequiredHandlePHIDs() {
    $required_phids = parent::getRequiredHandlePHIDs();
    $from_phid = $this->getStoryData()->getValue('movedFromPHID');
    if ($from_phid) {
      $required_phids[] = $from_phid;
    }
    return $required_phids;
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();
    $document_phid = $data->getValue('phid');

    $view = new PhabricatorFeedStoryView();

    $action = $data->getValue('action');
    $verb = PhrictionActionConstants::getActionPastTenseVerb($action);

    switch ($action) {
      case PhrictionActionConstants::ACTION_MOVE_HERE:
        $from_phid = $data->getValue('movedFromPHID');

        // Older feed stories may not have 'moved_from_phid', in that case
        // we fall back to the default behaviour (hence the fallthrough)
        if ($from_phid) {
          $document_handle = $this->getHandle($document_phid);
          $from_handle = $this->getHandle($from_phid);
          $view->setTitle(pht(
            '%s moved the document %s from %s to %s.',
            $this->linkTo($author_phid),
            $document_handle->renderLink(),
            phutil_tag(
              'a',
              array(
                'href'    => $from_handle->getURI(),
              ),
              $from_handle->getURI()),
            phutil_tag(
              'a',
              array(
                'href'    => $document_handle->getURI(),
              ),
              $document_handle->getURI())));
          break;
        }
        /* Fallthrough */
      default:
        $view->setTitle(pht(
          '%s %s the document %s.',
          $this->linkTo($author_phid),
          $verb,
          $this->linkTo($document_phid)));
        break;
    }

    $view->setEpoch($data->getEpoch());

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
