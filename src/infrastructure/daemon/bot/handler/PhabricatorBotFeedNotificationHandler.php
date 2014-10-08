<?php

/**
 * Watches the feed and puts notifications into channel(s) of choice.
 */
final class PhabricatorBotFeedNotificationHandler
  extends PhabricatorBotHandler {

  private $startupDelay = 30;
  private $lastSeenChronoKey = 0;

  private $typesNeedURI = array('DREV', 'TASK');

  private function shouldShowStory($story) {
    $story_objectphid = $story['objectPHID'];
    $story_text = $story['text'];

    $show = $this->getConfig('notification.types');

    if ($show) {
      $obj_type = phid_get_type($story_objectphid);
      if (!in_array(strtolower($obj_type), $show)) {
        return false;
      }
    }

    $verbosity = $this->getConfig('notification.verbosity', 3);

    $verbs = array();

    switch ($verbosity) {
      case 2:
        $verbs[] = array(
          'commented',
          'added',
          'changed',
          'resigned',
          'explained',
          'modified',
          'attached',
          'edited',
          'joined',
          'left',
          'removed',
        );
      // fallthrough
      case 1:
        $verbs[] = array(
          'updated',
          'accepted',
          'requested',
          'planned',
          'claimed',
          'summarized',
          'commandeered',
          'assigned',
        );
      // fallthrough
      case 0:
        $verbs[] = array(
          'created',
          'closed',
          'raised',
          'committed',
          'abandoned',
          'reclaimed',
          'reopened',
          'deleted',
        );
        break;

      case 3:
      default:
        return true;
    }

    $verbs = '/('.implode('|', array_mergev($verbs)).')/';

    if (preg_match($verbs, $story_text)) {
      return true;
    }

    return false;
  }

  public function receiveMessage(PhabricatorBotMessage $message) {
    return;
  }

  public function runBackgroundTasks() {
    if ($this->startupDelay > 0) {
      // the event loop runs every 1s so delay enough to fully conenct
      $this->startupDelay--;

      return;
    }
    if ($this->lastSeenChronoKey == 0) {
      // Since we only want to post notifications about new stories, skip
      // everything that's happened in the past when we start up so we'll
      // only process real-time stories.
      $latest = $this->getConduit()->callMethodSynchronous(
        'feed.query',
        array(
          'limit' => 1,
        ));

      foreach ($latest as $story) {
        if ($story['chronologicalKey'] > $this->lastSeenChronoKey) {
          $this->lastSeenChronoKey = $story['chronologicalKey'];
        }
      }

      return;
    }

    $config_max_pages = $this->getConfig('notification.max_pages', 5);
    $config_page_size = $this->getConfig('notification.page_size', 10);

    $last_seen_chrono_key = $this->lastSeenChronoKey;
    $chrono_key_cursor = 0;

    // Not efficient but works due to feed.query API
    for ($max_pages = $config_max_pages; $max_pages > 0; $max_pages--) {
      $stories = $this->getConduit()->callMethodSynchronous(
        'feed.query',
        array(
          'limit' => $config_page_size,
          'after' => $chrono_key_cursor,
          'view' => 'text',
        ));

      foreach ($stories as $story) {
        if ($story['chronologicalKey'] == $last_seen_chrono_key) {
          // Caught up on feed
          return;
        }
        if ($story['chronologicalKey'] > $this->lastSeenChronoKey) {
          // Keep track of newest seen story
          $this->lastSeenChronoKey = $story['chronologicalKey'];
        }
        if (!$chrono_key_cursor ||
            $story['chronologicalKey'] < $chrono_key_cursor) {
          // Keep track of oldest story on this page
          $chrono_key_cursor = $story['chronologicalKey'];
        }

        if (!$story['text'] ||
            !$this->shouldShowStory($story)) {
          continue;
        }

        $message = $story['text'];

        $story_object_type = phid_get_type($story['objectPHID']);
        if (in_array($story_object_type, $this->typesNeedURI)) {
          $objects = $this->getConduit()->callMethodSynchronous(
            'phid.lookup',
            array(
              'names' => array($story['objectPHID']),
            ));
          $message .= ' '.$objects[$story['objectPHID']]['uri'];
        }

        $channels = $this->getConfig('join');
        foreach ($channels as $channel_name) {

          $channel = id(new PhabricatorBotChannel())
            ->setName($channel_name);

          $this->writeMessage(
            id(new PhabricatorBotMessage())
            ->setCommand('MESSAGE')
            ->setTarget($channel)
            ->setBody($message));
        }
      }
    }
  }

}
