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
  private $notifyAuthor;
  private $mailTags = array();

  public function setMailTags(array $mail_tags) {
    $this->mailTags = $mail_tags;
    return $this;
  }

  public function getMailTags() {
    return $this->mailTags;
  }

  public function setNotifyAuthor($notify_author) {
    $this->notifyAuthor = $notify_author;
    return $this;
  }

  public function getNotifyAuthor() {
    return $this->notifyAuthor;
  }

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
      throw new Exception(
        pht(
          'Call %s before publishing!',
          'setStoryType()'));
    }

    if (!class_exists($class)) {
      throw new Exception(
        pht(
          "Story type must be a valid class name and must subclass %s. ".
          "'%s' is not a loadable class.",
          'PhabricatorFeedStory',
          $class));
    }

    if (!is_subclass_of($class, 'PhabricatorFeedStory')) {
      throw new Exception(
        pht(
          "Story type must be a valid class name and must subclass %s. ".
          "'%s' is not a subclass of %s.",
          'PhabricatorFeedStory',
          $class,
          'PhabricatorFeedStory'));
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

    $subscribed_phids = $this->subscribedPHIDs;
    if ($subscribed_phids) {
      $subscribed_phids = $this->filterSubscribedPHIDs($subscribed_phids);
      $this->insertNotifications($chrono_key, $subscribed_phids);
      $this->sendNotification($chrono_key, $subscribed_phids);
    }

    PhabricatorWorker::scheduleTask(
      'FeedPublisherWorker',
      array(
        'key' => $chrono_key,
      ));

    return $story;
  }

  private function insertNotifications($chrono_key, array $subscribed_phids) {
    if (!$this->primaryObjectPHID) {
      throw new Exception(
        pht(
          'You must call %s if you %s!',
          'setPrimaryObjectPHID()',
          'setSubscribedPHIDs()'));
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

    if ($sql) {
      queryfx(
        $conn,
        'INSERT INTO %T '.
        '(primaryObjectPHID, userPHID, chronologicalKey, hasViewed) '.
        'VALUES %Q',
        $notif->getTableName(),
        implode(', ', $sql));
    }
  }

  private function sendNotification($chrono_key, array $subscribed_phids) {
    $data = array(
      'key'         => (string)$chrono_key,
      'type'        => 'notification',
      'subscribers' => $subscribed_phids,
    );

    PhabricatorNotificationClient::tryToPostMessage($data);
  }

  /**
   * Remove PHIDs who should not receive notifications from a subscriber list.
   *
   * @param list<phid> List of potential subscribers.
   * @return list<phid> List of actual subscribers.
   */
  private function filterSubscribedPHIDs(array $phids) {
    $phids = $this->expandRecipients($phids);

    $tags = $this->getMailTags();
    if ($tags) {
      $all_prefs = id(new PhabricatorUserPreferences())->loadAllWhere(
        'userPHID in (%Ls)',
        $phids);
      $all_prefs = mpull($all_prefs, null, 'getUserPHID');
    }

    $pref_default = PhabricatorUserPreferences::MAILTAG_PREFERENCE_EMAIL;
    $pref_ignore = PhabricatorUserPreferences::MAILTAG_PREFERENCE_IGNORE;

    $keep = array();
    foreach ($phids as $phid) {
      if (($phid == $this->storyAuthorPHID) && !$this->getNotifyAuthor()) {
        continue;
      }

      if ($tags && isset($all_prefs[$phid])) {
        $mailtags = $all_prefs[$phid]->getPreference(
          PhabricatorUserPreferences::PREFERENCE_MAILTAGS,
          array());

        $notify = false;
        foreach ($tags as $tag) {
          // If this is set to "email" or "notify", notify the user.
          if ((int)idx($mailtags, $tag, $pref_default) != $pref_ignore) {
            $notify = true;
            break;
          }
        }

        if (!$notify) {
          continue;
        }
      }

      $keep[] = $phid;
    }

    return array_values(array_unique($keep));
  }

  private function expandRecipients(array $phids) {
    return id(new PhabricatorMetaMTAMemberQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->executeExpansion();
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
