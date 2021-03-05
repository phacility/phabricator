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
      'SELECT story.*, notification.hasViewed FROM %R notification
         JOIN %R story ON notification.chronologicalKey = story.chronologicalKey
         %Q
         ORDER BY notification.chronologicalKey DESC
         %Q',
      $notification_table,
      $story_table,
      $this->buildWhereClause($conn),
      $this->buildLimitClause($conn));

    // See T13623. Although most queries for notifications return unique
    // stories, this isn't a guarantee.
    $story_map = ipull($data, null, 'chronologicalKey');

    $stories = PhabricatorFeedStory::loadAllFromRows(
      $story_map,
      $this->getViewer());
    $stories = mpull($stories, null, 'getChronologicalKey');

    $results = array();
    foreach ($data as $row) {
      $story_key = $row['chronologicalKey'];
      $has_viewed = $row['hasViewed'];

      $results[] = id(clone $stories[$story_key])
        ->setHasViewed($has_viewed);
    }

    return $results;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'notification.userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->unread !== null) {
      $where[] = qsprintf(
        $conn,
        'notification.hasViewed = %d',
        (int)!$this->unread);
    }

    if ($this->keys !== null) {
      $where[] = qsprintf(
        $conn,
        'notification.chronologicalKey IN (%Ls)',
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

  protected function getDefaultOrderVector() {
    return array('key');
  }

  public function getBuiltinOrders() {
    return array(
      'newest' => array(
        'vector' => array('key'),
        'name' => pht('Creation (Newest First)'),
        'aliases' => array('created'),
      ),
      'oldest' => array(
        'vector' => array('-key'),
        'name' => pht('Creation (Oldest First)'),
      ),
    );
  }

  public function getOrderableColumns() {
    return array(
      'key' => array(
        'table' => 'notification',
        'column' => 'chronologicalKey',
        'type' => 'string',
        'unique' => true,
      ),
    );
  }

  protected function applyExternalCursorConstraintsToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $subquery,
    $cursor) {

    $subquery
      ->withKeys(array($cursor))
      ->setLimit(1);

  }

  protected function newExternalCursorStringForResult($object) {
    return $object->getChronologicalKey();
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'key' => $object->getChronologicalKey(),
    );
  }

  protected function getPrimaryTableAlias() {
    return 'notification';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorNotificationsApplication';
  }

}
