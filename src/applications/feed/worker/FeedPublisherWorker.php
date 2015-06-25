<?php

final class FeedPublisherWorker extends FeedPushWorker {

  protected function doWork() {
    $story = $this->loadFeedStory();

    $uris = PhabricatorEnv::getEnvConfig('feed.http-hooks');

    if ($uris) {
      foreach ($uris as $uri) {
        $this->queueTask(
          'FeedPublisherHTTPWorker',
          array(
            'key' => $story->getChronologicalKey(),
            'uri' => $uri,
          ));
      }
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
      $this->queueTask(
        get_class($worker),
        array(
          'key' => $story->getChronologicalKey(),
        ));
    }
  }


}
