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

    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
        $one_line = pht('%s commented on revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_ACCEPT:
        $one_line = pht('%s accepted revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_REJECT:
        $one_line = pht('%s requested changes to revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_RETHINK:
        $one_line = pht('%s planned changes to revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_ABANDON:
        $one_line = pht('%s abandoned revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_CLOSE:
        $one_line = pht('%s closed revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_REQUEST:
        $one_line = pht('%s requested a review of revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_RECLAIM:
        $one_line = pht('%s reclaimed revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_UPDATE:
        $one_line = pht('%s updated revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_RESIGN:
        $one_line = pht('%s resigned from revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_SUMMARIZE:
        $one_line = pht('%s summarized revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_TESTPLAN:
        $one_line = pht('%s explained the test plan for revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_CREATE:
        $one_line = pht('%s created revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_ADDREVIEWERS:
        $one_line = pht('%s added reviewers to revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_ADDCCS:
        $one_line = pht('%s added CCs to revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_CLAIM:
        $one_line = pht('%s commandeered revision %s',
          $actor_link, $revision_link);
      break;
      case DifferentialAction::ACTION_REOPEN:
        $one_line = pht('%s reopened revision %s',
        $actor_link, $revision_link);
      break;
      case DifferentialTransaction::TYPE_INLINE:
        $one_line = pht('%s added inline comments to %s',
        $actor_link, $revision_link);
      break;
      default:
        $one_line = pht('%s edited %s',
        $actor_link, $revision_link);
      break;
    }

    return $one_line;
  }

  public function renderText() {
    $author_name = $this->getHandle($this->getAuthorPHID())->getLinkName();

    $revision_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $revision_title = $revision_handle->getLinkName();
    $revision_uri = PhabricatorEnv::getURI($revision_handle->getURI());

    $action = $this->getValue('action');

    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
        $one_line = pht('%s commented on revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_ACCEPT:
        $one_line = pht('%s accepted revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_REJECT:
        $one_line = pht('%s requested changes to revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_RETHINK:
        $one_line = pht('%s planned changes to revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_ABANDON:
        $one_line = pht('%s abandoned revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_CLOSE:
        $one_line = pht('%s closed revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_REQUEST:
        $one_line = pht('%s requested a review of revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_RECLAIM:
        $one_line = pht('%s reclaimed revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_UPDATE:
        $one_line = pht('%s updated revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_RESIGN:
        $one_line = pht('%s resigned from revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_SUMMARIZE:
        $one_line = pht('%s summarized revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_TESTPLAN:
        $one_line = pht('%s explained the test plan for revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_CREATE:
        $one_line = pht('%s created revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_ADDREVIEWERS:
        $one_line = pht('%s added reviewers to revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_ADDCCS:
        $one_line = pht('%s added CCs to revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_CLAIM:
        $one_line = pht('%s commandeered revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialAction::ACTION_REOPEN:
        $one_line = pht('%s reopened revision %s %s',
          $author_name, $revision_title, $revision_uri);
      break;
      case DifferentialTransaction::TYPE_INLINE:
        $one_line = pht('%s added inline comments to %s %s',
          $author_name, $revision_title, $revision_uri);
        break;
      default:
        $one_line = pht('%s edited %s %s',
          $author_name, $revision_title, $revision_uri);
        break;
    }

    return $one_line;
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
  public function renderForAsanaBridge($implied_context = false) {
    $data = $this->getStoryData();
    $comment = $data->getValue('feedback_content');

    $author_name = $this->getHandle($this->getAuthorPHID())->getName();
    $action = $this->getValue('action');

    $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', new PhabricatorUser())
      ->setMode(PhutilRemarkupEngine::MODE_TEXT);

    $revision_phid = $this->getPrimaryObjectPHID();
    $revision_name = $this->getHandle($revision_phid)->getFullName();

    if ($implied_context) {
      $title = DifferentialAction::getBasicStoryText(
            $action, $author_name);
    } else {
      switch ($action) {
        case DifferentialAction::ACTION_COMMENT:
          $title = pht('%s commented on revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_ACCEPT:
          $title = pht('%s accepted revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_REJECT:
          $title = pht('%s requested changes to revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_RETHINK:
          $title = pht('%s planned changes to revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_ABANDON:
          $title = pht('%s abandoned revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_CLOSE:
          $title = pht('%s closed revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_REQUEST:
          $title = pht('%s requested a review of revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_RECLAIM:
          $title = pht('%s reclaimed revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_UPDATE:
          $title = pht('%s updated revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_RESIGN:
          $title = pht('%s resigned from revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_SUMMARIZE:
          $title = pht('%s summarized revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_TESTPLAN:
          $title = pht('%s explained the test plan for revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_CREATE:
          $title = pht('%s created revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_ADDREVIEWERS:
          $title = pht('%s added reviewers to revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_ADDCCS:
          $title = pht('%s added CCs to revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_CLAIM:
          $title = pht('%s commandeered revision %s',
            $author_name, $revision_name);
        break;
        case DifferentialAction::ACTION_REOPEN:
          $title = pht('%s reopened revision %s',
          $author_name, $revision_name);
        break;
        case DifferentialTransaction::TYPE_INLINE:
          $title = pht('%s added inline comments to %s',
          $author_name, $revision_name);
        break;
        default:
          $title = pht('%s edited revision %s',
          $author_name, $revision_name);
        break;
      }
    }

    if (strlen($comment)) {
      $comment = $engine->markupText($comment);

      $title .= "\n\n";
      $title .= $comment;
    }

    // Roughly render inlines into the comment.
    $xaction_phids = $data->getValue('temporaryTransactionPHIDs');
    if ($xaction_phids) {
      $inlines = id(new DifferentialTransactionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($xaction_phids)
        ->needComments(true)
        ->withTransactionTypes(
          array(
            DifferentialTransaction::TYPE_INLINE,
          ))
        ->execute();
      if ($inlines) {
        $title .= "\n\n";
        $title .= pht('Inline Comments');
        $title .= "\n";

        $changeset_ids = array();
        foreach ($inlines as $inline) {
          $changeset_ids[] = $inline->getComment()->getChangesetID();
        }

        $changesets = id(new DifferentialChangeset())->loadAllWhere(
          'id IN (%Ld)',
          $changeset_ids);

        foreach ($inlines as $inline) {
          $comment = $inline->getComment();
          $changeset = idx($changesets, $comment->getChangesetID());
          if (!$changeset) {
            continue;
          }

          $filename = $changeset->getDisplayFilename();
          $linenumber = $comment->getLineNumber();
          $inline_text = $engine->markupText($comment->getContent());
          $inline_text = rtrim($inline_text);

          $title .= "{$filename}:{$linenumber} {$inline_text}\n";
        }
      }
    }


    return $title;
  }


}
