<?php

final class FeedPublisherWorker extends FeedPushWorker {

  protected function doWork() {
    $story = $this->loadFeedStory();

    $uris = PhabricatorEnv::getEnvConfig('feed.http-hooks');
    foreach ($uris as $uri) {
      PhabricatorWorker::scheduleTask(
        'FeedPublisherHTTPWorker',
        array(
          'key' => $story->getChronologicalKey(),
          'uri' => $uri,
        ));
    }

    if (PhabricatorEnv::getEnvConfig('asana.workspace-id')) {
      PhabricatorWorker::scheduleTask(
        'DoorkeeperFeedWorkerAsana',
        array(
          'key' => $story->getChronologicalKey(),
        ));
    }

  }


}
