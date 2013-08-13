<?php

final class PhabricatorFeedStoryDifferential extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('revision_phid');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = $this->newStoryView();
    $view->setAppIcon('differential-dark');

    $line = $this->getLineForData($data);
    $view->setTitle($line);

    $href = $this->getHandle($data->getValue('revision_phid'))->getURI();
    $view->setHref($href);

    $action = $data->getValue('action');

    switch ($action) {
      case DifferentialAction::ACTION_CREATE:
      case DifferentialAction::ACTION_CLOSE:
      case DifferentialAction::ACTION_COMMENT:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
    if ($full_size) {
      $content = $this->renderSummary($data->getValue('feedback_content'));
      $view->appendChild($content);
    }

    return $view;
  }

  private function getLineForData($data) {
    $actor_phid = $data->getAuthorPHID();
    $revision_phid = $data->getValue('revision_phid');
    $action = $data->getValue('action');

    $actor_link = $this->linkTo($actor_phid);
    $revision_link = $this->linkTo($revision_phid);

    $verb = DifferentialAction::getActionPastTenseVerb($action);

    $one_line = hsprintf(
      '%s %s revision %s',
      $actor_link,
      $verb,
      $revision_link);

    return $one_line;
  }

  public function renderText() {
    $author_name = $this->getHandle($this->getAuthorPHID())->getLinkName();

    $revision_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $revision_title = $revision_handle->getLinkName();
    $revision_uri = PhabricatorEnv::getURI($revision_handle->getURI());

    $action = $this->getValue('action');
    $verb = DifferentialAction::getActionPastTenseVerb($action);

    $text = "{$author_name} {$verb} revision {$revision_title} {$revision_uri}";

    return $text;
  }

  public function getNotificationAggregations() {
    $class = get_class($this);
    $phid  = $this->getStoryData()->getValue('revision_phid');
    $read  = (int)$this->getHasViewed();

    // Don't aggregate updates separated by more than 2 hours.
    $block = (int)($this->getEpoch() / (60 * 60 * 2));

    return array(
      "{$class}:{$phid}:{$read}:{$block}"
        => 'PhabricatorFeedStoryDifferentialAggregate',
    );
  }

  // TODO: At some point, make feed rendering not terrible and remove this
  // hacky mess.
  public function renderForAsanaBridge() {
    $data = $this->getStoryData();
    $comment = $data->getValue('feedback_content');

    $author_name = $this->getHandle($this->getAuthorPHID())->getName();
    $action = $this->getValue('action');
    $verb = DifferentialAction::getActionPastTenseVerb($action);

    $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', new PhabricatorUser())
      ->setMode(PhutilRemarkupEngine::MODE_TEXT);

    $title = "{$author_name} {$verb} this revision.";
    if (strlen($comment)) {
      $comment = $engine->markupText($comment);

      $title .= "\n\n";
      $title .= $comment;
    }

    // Roughly render inlines into the comment.
    $comment_id = $data->getValue('temporaryCommentID');
    if ($comment_id) {
      $inlines = id(new DifferentialInlineCommentQuery())
        ->withCommentIDs(array($comment_id))
        ->execute();
      if ($inlines) {
        $title .= "\n\n";
        $title .= pht('Inline Comments');
        $title .= "\n";
        $changeset_ids = mpull($inlines, 'getChangesetID');
        $changesets = id(new DifferentialChangeset())->loadAllWhere(
          'id IN (%Ld)',
          $changeset_ids);
        foreach ($inlines as $inline) {
          $changeset = idx($changesets, $inline->getChangesetID());
          if (!$changeset) {
            continue;
          }

          $filename = $changeset->getDisplayFilename();
          $linenumber = $inline->getLineNumber();
          $inline_text = $engine->markupText($inline->getContent());
          $inline_text = rtrim($inline_text);

          $title .= "{$filename}:{$linenumber} {$inline_text}\n";
        }
      }
    }


    return $title;
  }


}
