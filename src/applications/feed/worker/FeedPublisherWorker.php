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

    $argv = array(
      array(),
    );

    // Find and schedule all the enabled Doorkeeper publishers.
    $doorkeeper_workers = id(new PhutilSymbolLoader())
      ->setAncestorClass('DoorkeeperFeedWorker')
      ->loadObjects($argv);
    foreach ($doorkeeper_workers as $worker) {
      if (!$worker->isEnabled()) {
        continue;
      }
      PhabricatorWorker::scheduleTask(
        get_class($worker),
        array(
          'key' => $story->getChronologicalKey(),
        ));
    }
  }


}
