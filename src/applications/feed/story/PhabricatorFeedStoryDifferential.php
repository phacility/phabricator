<?php

final class PhabricatorFeedStoryDifferential extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('revision_phid');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PhabricatorFeedStoryView();

    $line = $this->getLineForData($data);
    $view->setTitle($line);
    $view->setEpoch($data->getEpoch());

    $action = $data->getValue('action');
    switch ($action) {
      case DifferentialAction::ACTION_CREATE:
      case DifferentialAction::ACTION_CLOSE:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    if ($full_size) {
      $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
      $content = $this->renderSummary($data->getValue('feedback_content'));
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
    $actor_phid = $data->getAuthorPHID();
    $revision_phid = $data->getValue('revision_phid');
    $action = $data->getValue('action');

    $actor_link = $this->linkTo($actor_phid);
    $revision_link = $this->linkTo($revision_phid);

    $verb = DifferentialAction::getActionPastTenseVerb($action);

    $one_line = "{$actor_link} {$verb} revision {$revision_link}";

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
