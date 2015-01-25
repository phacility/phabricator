<?php

final class PhabricatorFeedStoryDifferential extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('revision_phid');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = $this->newStoryView();

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

}
