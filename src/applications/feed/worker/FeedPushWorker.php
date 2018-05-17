<?php

abstract class FeedPushWorker extends PhabricatorWorker {

  protected function loadFeedStory() {
    $task_data = $this->getTaskData();
    $key = $task_data['key'];

    $story = id(new PhabricatorFeedQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withChronologicalKeys(array($key))
      ->executeOne();

    if (!$story) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Feed story (with key "%s") does not exist or could not be loaded.',
          $key));
    }

    return $story;
  }

}
