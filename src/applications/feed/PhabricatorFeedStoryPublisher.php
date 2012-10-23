<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorFeedStoryPublisher {

  private $relatedPHIDs;
  private $storyType;
  private $storyData;
  private $storyTime;
  private $storyAuthorPHID;
  private $primaryObjectPHID;
  private $subscribedPHIDs = array();

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

    if (PhabricatorEnv::getEnvConfig('notification.enabled')) {
      $this->insertNotifications($chrono_key);
      $this->sendNotification($chrono_key);
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

    foreach (array_unique($subscribed_phids) as $user_phid) {
      $sql[] = qsprintf(
        $conn,
        '(%s, %s, %s, %d)',
        $this->primaryObjectPHID,
        $user_phid,
        $chrono_key,
        0);
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
