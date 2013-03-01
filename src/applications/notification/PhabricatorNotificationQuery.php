<?php

/**
 * @task config Configuring the Query
 * @task exec   Query Execution
 */
final class PhabricatorNotificationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $userPHID;
  private $keys;
  private $unread;


/* -(  Configuring the Query  )---------------------------------------------- */


  public function setUserPHID($user_phid) {
    $this->userPHID = $user_phid;
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
    if (!$this->userPHID) {
      throw new Exception("Call setUser() before executing the query");
    }

    $story_table = new PhabricatorFeedStoryData();
    $notification_table = new PhabricatorFeedStoryNotification();

    $conn = $story_table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      "SELECT story.*, notif.hasViewed FROM %T notif
         JOIN %T story ON notif.chronologicalKey = story.chronologicalKey
         %Q
         ORDER BY notif.chronologicalKey DESC
         %Q",
      $notification_table->getTableName(),
      $story_table->getTableName(),
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

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->userPHID) {
      $where[] = qsprintf(
        $conn_r,
        'notif.userPHID = %s',
        $this->userPHID);
    }

    if ($this->unread !== null) {
      $where[] = qsprintf(
        $conn_r,
        'notif.hasViewed = %d',
        (int)!$this->unread);
    }

    if ($this->keys) {
      $where[] = qsprintf(
        $conn_r,
        'notif.chronologicalKey IN (%Ls)',
        $this->keys);
    }

    return $this->formatWhereClause($where);
  }

}
