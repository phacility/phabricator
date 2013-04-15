<?php

final class PhabricatorFeedStoryDifferential extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('revision_phid');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PHUIFeedStoryView();
    $view->setAppIcon('differential-dark');
    $view->setViewed($this->getHasViewed());

    $line = $this->getLineForData($data);
    $view->setTitle($line);
    $view->setEpoch($data->getEpoch());

    $href = $this->getHandle($data->getValue('revision_phid'))->getURI();
    $view->setHref($href);

    $action = $data->getValue('action');

    $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
    $content = $this->renderSummary($data->getValue('feedback_content'));
    $view->appendChild($content);

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
}
