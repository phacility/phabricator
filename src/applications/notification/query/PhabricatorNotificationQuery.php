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

    return $data;
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

  protected function willFilterPage(array $rows) {
    // See T13623. The policy model here is outdated and awkward.

    // Users may have notifications about objects they can no longer see.
    // Two ways this can arise: destroy an object; or change an object's
    // view policy to exclude a user.

    // "PhabricatorFeedStory::loadAllFromRows()" does its own policy filtering.
    // This doesn't align well with modern query sequencing, but we should be
    // able to get away with it by loading here.

    // See T13623. Although most queries for notifications return unique
    // stories, this isn't a guarantee.
    $story_map = ipull($rows, null, 'chronologicalKey');

    $viewer = $this->getViewer();
    $stories = PhabricatorFeedStory::loadAllFromRows($story_map, $viewer);
    $stories = mpull($stories, null, 'getChronologicalKey');

    $results = array();
    foreach ($rows as $row) {
      $story_key = $row['chronologicalKey'];
      $has_viewed = $row['hasViewed'];

      if (!isset($stories[$story_key])) {
        // NOTE: We can't call "didRejectResult()" here because we don't have
        // a policy object to pass.
        continue;
      }

      $story = id(clone $stories[$story_key])
        ->setHasViewed($has_viewed);

      if (!$story->isVisibleInNotifications()) {
        continue;
      }

      $results[] = $story;
    }

    return $results;
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
      'key' => $object['chronologicalKey'],
    );
  }

  protected function getPrimaryTableAlias() {
    return 'notification';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorNotificationsApplication';
  }

}
