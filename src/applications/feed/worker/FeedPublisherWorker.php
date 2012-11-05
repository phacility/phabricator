<?php

final class FeedPublisherWorker extends PhabricatorWorker {

  protected function doWork() {
    $task_data  = $this->getTaskData();
    $chrono_key = $task_data['chrono_key'];
    $uri        = $task_data['uri'];

    $story = id(new PhabricatorFeedStoryData())
      ->loadOneWhere('chronologicalKey = %s', $chrono_key);

    if (!$story) {
      throw new PhabricatorWorkerPermanentFailureException(
        'Feed story was deleted.'
      );
    }

    $data = array(
      'storyID'         => $story->getID(),
      'storyType'       => $story->getStoryType(),
      'storyData'       => $story->getStoryData(),
      'storyAuthorPHID' => $story->getAuthorPHID(),
      'epoch'           => $story->getEpoch(),
    );

    id(new HTTPFuture($uri, $data))
      ->setMethod('POST')
      ->setTimeout(30)
      ->resolvex();

  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return max($task->getFailureCount(), 1) * 60;
  }

}
