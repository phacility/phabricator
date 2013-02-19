<?php

final class PhabricatorFeedStoryPublisher {

  private $relatedPHIDs;
  private $storyType;
  private $storyData;
  private $storyTime;
  private $storyAuthorPHID;
  private $primaryObjectPHID;
  private $subscribedPHIDs = array();
  private $mailRecipientPHIDs = array();

  public function setRelatedPHIDs(array $phids) {
    $this->relatedPHIDs = $phids;
    return $this;
  }

  public function setSubscribedPHIDs(array $phids) {
    $this->subscribedPHIDs = $phids;
    return $this;
  }
  public function setPrimaryObjectPHID($phid) {
    $this->primaryObjectPHID = $phid;
    return $this;
  }

  public function setStoryType($story_type) {
    $this->storyType = $story_type;
    return $this;
  }

  public function setStoryData(array $data) {
    $this->storyData = $data;
    return $this;
  }

  public function setStoryTime($time) {
    $this->storyTime = $time;
    return $this;
  }

  public function setStoryAuthorPHID($phid) {
    $this->storyAuthorPHID = $phid;
    return $this;
  }

  public function setMailRecipientPHIDs(array $phids) {
    $this->mailRecipientPHIDs = $phids;
    return $this;
  }

  public function publish() {
    $class = $this->storyType;
    if (!$class) {
      throw new Exception("Call setStoryType() before publishing!");
    }

    if (!class_exists($class)) {
      throw new Exception(
        "Story type must be a valid class name and must subclass ".
        "PhabricatorFeedStory. ".
        "'{$class}' is not a loadable class.");
    }

    if (!is_subclass_of($class, 'PhabricatorFeedStory')) {
      throw new Exception(
        "Story type must be a valid class name and must subclass ".
        "PhabricatorFeedStory. ".
        "'{$class}' is not a subclass of PhabricatorFeedStory.");
    }

    $chrono_key = $this->generateChronologicalKey();

    $story = new PhabricatorFeedStoryData();
    $story->setStoryType($this->storyType);
    $story->setStoryData($this->storyData);
    $story->setAuthorPHID((string)$this->storyAuthorPHID);
    $story->setChronologicalKey($chrono_key);
    $story->save();

    if ($this->relatedPHIDs) {
      $ref = new PhabricatorFeedStoryReference();

      $sql = array();
      $conn = $ref->establishConnection('w');
      foreach (array_unique($this->relatedPHIDs) as $phid) {
        $sql[] = qsprintf(
          $conn,
          '(%s, %s)',
          $phid,
          $chrono_key);
      }

      queryfx(
        $conn,
        'INSERT INTO %T (objectPHID, chronologicalKey) VALUES %Q',
        $ref->getTableName(),
        implode(', ', $sql));
    }

    $this->insertNotifications($chrono_key);
    if (PhabricatorEnv::getEnvConfig('notification.enabled')) {
      $this->sendNotification($chrono_key);
    }

    $uris = PhabricatorEnv::getEnvConfig('feed.http-hooks');
    foreach ($uris as $uri) {
      $task = PhabricatorWorker::scheduleTask(
        'FeedPublisherWorker',
        array('chrono_key' => $chrono_key, 'uri' => $uri));
    }

    return $story;
  }

  private function insertNotifications($chrono_key) {
    $subscribed_phids = $this->subscribedPHIDs;
    $subscribed_phids = array_diff(
      $subscribed_phids,
      array($this->storyAuthorPHID));

    if (!$subscribed_phids) {
      return;
    }

    if (!$this->primaryObjectPHID) {
      throw new Exception(
        "You must call setPrimaryObjectPHID() if you setSubscribedPHIDs()!");
    }

    $notif = new PhabricatorFeedStoryNotification();
    $sql = array();
    $conn = $notif->establishConnection('w');

    $will_receive_mail = array_fill_keys($this->mailRecipientPHIDs, true);

    foreach (array_unique($subscribed_phids) as $user_phid) {
      if (isset($will_receive_mail[$user_phid])) {
        $mark_read = 1;
      } else {
        $mark_read = 0;
      }

      $sql[] = qsprintf(
        $conn,
        '(%s, %s, %s, %d)',
        $this->primaryObjectPHID,
        $user_phid,
        $chrono_key,
        $mark_read);
    }

    queryfx(
      $conn,
      'INSERT INTO %T
     (primaryObjectPHID, userPHID, chronologicalKey, hasViewed)
     VALUES %Q',
      $notif->getTableName(),
      implode(', ', $sql));
  }

  private function sendNotification($chrono_key) {
    $server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');

    $data = array(
      'key' => (string)$chrono_key,
    );

    id(new HTTPSFuture($server_uri, $data))
      ->setMethod('POST')
      ->setTimeout(1)
      ->resolve();
  }

  /**
   * We generate a unique chronological key for each story type because we want
   * to be able to page through the stream with a cursor (i.e., select stories
   * after ID = X) so we can efficiently perform filtering after selecting data,
   * and multiple stories with the same ID make this cumbersome without putting
   * a bunch of logic in the client. We could use the primary key, but that
   * would prevent publishing stories which happened in the past. Since it's
   * potentially useful to do that (e.g., if you're importing another data
   * source) build a unique key for each story which has chronological ordering.
   *
   * @return string A unique, time-ordered key which identifies the story.
   */
  private function generateChronologicalKey() {
    // Use the epoch timestamp for the upper 32 bits of the key. Default to
    // the current time if the story doesn't have an explicit timestamp.
    $time = nonempty($this->storyTime, time());

    // Generate a random number for the lower 32 bits of the key.
    $rand = head(unpack('L', Filesystem::readRandomBytes(4)));

    // On 32-bit machines, we have to get creative.
    if (PHP_INT_SIZE < 8) {
      // We're on a 32-bit machine.
      if (function_exists('bcadd')) {
        // Try to use the 'bc' extension.
        return bcadd(bcmul($time, bcpow(2, 32)), $rand);
      } else {
        // Do the math in MySQL. TODO: If we formalize a bc dependency, get
        // rid of this.
        $conn_r = id(new PhabricatorFeedStoryData())->establishConnection('r');
        $result = queryfx_one(
          $conn_r,
          'SELECT (%d << 32) + %d as N',
          $time,
          $rand);
        return $result['N'];
      }
    } else {
      // This is a 64 bit machine, so we can just do the math.
      return ($time << 32) + $rand;
    }
  }
}
