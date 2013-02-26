<?php

final class PhabricatorFeedStoryManiphest
  extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('taskPHID');
  }

  public function getRequiredHandlePHIDs() {
    return array(
      $this->getValue('ownerPHID'),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PhabricatorFeedStoryView();
    $view->setViewed($this->getHasViewed());

    $line = $this->getLineForData($data);
    $view->setTitle($line);
    $view->setEpoch($data->getEpoch());

    $action = $data->getValue('action');
    switch ($action) {
      case ManiphestAction::ACTION_CREATE:
      case ManiphestAction::ACTION_COMMENT:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());

      switch ($action) {
        case ManiphestAction::ACTION_COMMENT:
          // I'm just fetching the comments here
          // Don't repeat this at home!
          $comments = $data->getValue('comments');
          $content = $this->renderSummary($comments);
          break;
        default:
          // I think this is just for create
          $content = $this->renderSummary($data->getValue('description'));
          break;
      }

      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    $href = $this->getHandle($data->getValue('taskPHID'))->getURI();
    $view->setHref($href);

    return $view;
  }

  private function getLineForData($data) {
    $action = $data->getValue('action');

    $actor_phid = $data->getAuthorPHID();
    $actor_link = $this->linkTo($actor_phid);

    $task_phid = $data->getValue('taskPHID');
    $task_link = $this->linkTo($task_phid);

    $owner_phid = $data->getValue('ownerPHID');
    $owner_link = $this->linkTo($owner_phid);

    $verb = ManiphestAction::getActionPastTenseVerb($action);

    switch ($action) {
      case ManiphestAction::ACTION_ASSIGN:
      case ManiphestAction::ACTION_REASSIGN:
        if ($owner_phid) {
          if ($owner_phid == $actor_phid) {
            $one_line = hsprintf('%s claimed %s', $actor_link, $task_link);
          } else {
            $one_line = hsprintf('%s %s %s to %s',
              $actor_link,
              $verb,
              $owner_link,
              $task_link);
          }
        } else {
          $one_line = hsprintf(
            '%s placed %s up for grabs',
            $actor_link,
            $task_link);
        }
        break;
      default:
        $one_line = hsprintf('%s %s %s', $actor_link, $verb, $task_link);
        break;
    }

    return $one_line;
  }

  public function renderText() {
    $actor_phid = $this->getAuthorPHID();
    $author_name = $this->getHandle($actor_phid)->getLinkName();

    $owner_phid = $this->getValue('ownerPHID');
    $owner_name = $this->getHandle($owner_phid)->getLinkName();

    $task_phid = $this->getPrimaryObjectPHID();
    $task_handle = $this->getHandle($task_phid);
    $task_title = $task_handle->getLinkName();
    $task_uri = PhabricatorEnv::getURI($task_handle->getURI());

    $action = $this->getValue('action');
    $verb = ManiphestAction::getActionPastTenseVerb($action);

    switch ($action) {
      case ManiphestAction::ACTION_ASSIGN:
      case ManiphestAction::ACTION_REASSIGN:
        if ($owner_phid) {
          if ($owner_phid == $actor_phid) {
            $text = "{$author_name} claimed {$task_title}";
          } else {
            $text = "{$author_name} {$verb} {$task_title} to {$owner_name}";
          }
        } else {
          $text = "{$author_name} placed {$task_title} up for grabs";
        }
        break;
      default:
        $text = "{$author_name} {$verb} {$task_title}";
        break;
    }

    $text .= " {$task_uri}";

    return $text;
  }

  public function getNotificationAggregations() {
    $class = get_class($this);
    $phid  = $this->getStoryData()->getValue('taskPHID');
    $read  = (int)$this->getHasViewed();

    // Don't aggregate updates separated by more than 2 hours.
    $block = (int)($this->getEpoch() / (60 * 60 * 2));

    return array(
      "{$class}:{$phid}:{$read}:{$block}"
        => 'PhabricatorFeedStoryManiphestAggregate',
    );
  }

}
