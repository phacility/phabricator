<?php

final class PhabricatorFeedStoryPublisher extends Phobject {

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
  private $unexpandablePHIDs = array();

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

  public function setUnexpandablePHIDs(array $unexpandable_phids) {
    $this->unexpandablePHIDs = $unexpandable_phids;
    return $this;
  }

  public function getUnexpandablePHIDs() {
    return $this->unexpandablePHIDs;
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
        'INSERT INTO %T (objectPHID, chronologicalKey) VALUES %LQ',
        $ref->getTableName(),
        $sql);
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

    $user_phids = array_unique($subscribed_phids);
    foreach ($user_phids as $user_phid) {
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
        'VALUES %LQ',
        $notif->getTableName(),
        $sql);
    }

    PhabricatorUserCache::clearCaches(
      PhabricatorUserNotificationCountCacheType::KEY_COUNT,
      $user_phids);
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
      $all_prefs = id(new PhabricatorUserPreferencesQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withUserPHIDs($phids)
        ->needSyntheticPreferences(true)
        ->execute();
      $all_prefs = mpull($all_prefs, null, 'getUserPHID');
    }

    $pref_default = PhabricatorEmailTagsSetting::VALUE_EMAIL;
    $pref_ignore = PhabricatorEmailTagsSetting::VALUE_IGNORE;

    $keep = array();
    foreach ($phids as $phid) {
      if (($phid == $this->storyAuthorPHID) && !$this->getNotifyAuthor()) {
        continue;
      }

      if ($tags && isset($all_prefs[$phid])) {
        $mailtags = $all_prefs[$phid]->getSettingValue(
          PhabricatorEmailTagsSetting::SETTINGKEY);

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
    $expanded_phids = id(new PhabricatorMetaMTAMemberQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->executeExpansion();

    // Filter out unexpandable PHIDs from the results. The typical case for
    // this is that resigned reviewers should not be notified just because
    // they are a member of some project or package reviewer.

    $original_map = array_fuse($phids);
    $unexpandable_map = array_fuse($this->unexpandablePHIDs);

    foreach ($expanded_phids as $key => $phid) {
      // We can keep this expanded PHID if it was present originally.
      if (isset($original_map[$phid])) {
        continue;
      }

      // We can also keep it if it isn't marked as unexpandable.
      if (!isset($unexpandable_map[$phid])) {
        continue;
      }

      // If it's unexpandable and we produced it by expanding recipients,
      // throw it away.
      unset($expanded_phids[$key]);
    }
    $expanded_phids = array_values($expanded_phids);

    return $expanded_phids;
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
