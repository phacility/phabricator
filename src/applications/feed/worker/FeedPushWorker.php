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
        pht('Feed story does not exist.'));
    }

    return $story;
  }

}
