<?php

/**
 * @task config Configuring the Query
 * @task exec   Query Execution
 */
final class PhabricatorNotificationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $userPHIDs;
  private $keys;
  private $unread;


/* -(  Configuring the Query  )---------------------------------------------- */


  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withKeys(array $keys) {
    $this->keys = $keys;
    return $this;
  }


  /**
   * Filter results by read/unread status. Note that `true` means to return
   * only unread notifications, while `false` means to return only //read//
   * notifications. The default is `null`, which returns both.
   *
   * @param mixed True or false to filter results by read status. Null to remove
   *              the filter.
   * @return this
   * @task config
   */
  public function withUnread($unread) {
    $this->unread = $unread;
    return $this;
  }


/* -(  Query Execution  )---------------------------------------------------- */


  protected function loadPage() {
    $story_table = new PhabricatorFeedStoryData();
    $notification_table = new PhabricatorFeedStoryNotification();

    $conn = $story_table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      'SELECT story.*, notif.hasViewed FROM %R notif
         JOIN %R story ON notif.chronologicalKey = story.chronologicalKey
         %Q
         ORDER BY notif.chronologicalKey DESC
         %Q',
      $notification_table,
      $story_table,
      $this->buildWhereClause($conn),
      $this->buildLimitClause($conn));

    $viewed_map = ipull($data, 'hasViewed', 'chronologicalKey');

    $stories = PhabricatorFeedStory::loadAllFromRows(
      $data,
      $this->getViewer());

    foreach ($stories as $key => $story) {
      $story->setHasViewed($viewed_map[$key]);
    }

    return $stories;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'notif.userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->unread !== null) {
      $where[] = qsprintf(
        $conn,
        'notif.hasViewed = %d',
        (int)!$this->unread);
    }

    if ($this->keys !== null) {
      $where[] = qsprintf(
        $conn,
        'notif.chronologicalKey IN (%Ls)',
        $this->keys);
    }

    return $where;
  }

  protected function willFilterPage(array $stories) {
    foreach ($stories as $key => $story) {
      if (!$story->isVisibleInNotifications()) {
        unset($stories[$key]);
      }
    }

    return $stories;
  }

  protected function getResultCursor($item) {
    return $item->getChronologicalKey();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorNotificationsApplication';
  }

}
