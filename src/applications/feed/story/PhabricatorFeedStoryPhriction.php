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
    $author_link = $this->linkTo($author_phid);
    $document_phid = $data->getValue('phid');

    $view = $this->newStoryView();

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
            $author_link,
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
          $author_link,
          $verb,
          $this->linkTo($document_phid)));
        break;
    }

    $view->setImage($this->getHandle($author_phid)->getImageURI());
    switch ($action) {
      case PhrictionActionConstants::ACTION_CREATE:
        $content = $this->renderSummary($data->getValue('content'));
        $view->appendChild($content);
        break;
      }

    return $view;
  }

  public function renderText() {
    $author_handle = $this->getHandle($this->getAuthorPHID());
    $author_name = $author_handle->getName();

    $document_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $document_title = $document_handle->getLinkName();
    $document_uri = PhabricatorEnv::getURI($document_handle->getURI());

    $action = $this->getValue('action');
    $verb = PhrictionActionConstants::getActionPastTenseVerb($action);

    $text = "{$author_name} {$verb} the document".
            " {$document_title} {$document_uri}";

    return $text;
  }

}
