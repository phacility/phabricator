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

    $line = $this->getLineForData($data);
    $view->setTitle($line);
    $view->setEpoch($data->getEpoch());

    $action = $data->getValue('action');
    switch ($action) {
      case ManiphestAction::ACTION_CREATE:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
      $content = $this->renderSummary($data->getValue('description'));
      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

  public function renderNotificationView() {
    $data = $this->getStoryData();

    $view = new PhabricatorNotificationStoryView();

    $view->setTitle($this->getLineForData($data));
    $view->setEpoch($data->getEpoch());
    $view->setViewed($this->getHasViewed());

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
            $one_line = "{$actor_link} claimed {$task_link}";
          } else {
            $one_line = "{$actor_link} {$verb} {$task_link} to {$owner_link}";
          }
        } else {
          $one_line = "{$actor_link} placed {$task_link} up for grabs";
        }
        break;
      default:
        $one_line = "{$actor_link} {$verb} {$task_link}";
        break;
    }

    return $one_line;
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
