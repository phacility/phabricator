<?php

final class PhrequentUserTimeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const ORDER_ID_ASC        = 0;
  const ORDER_ID_DESC       = 1;
  const ORDER_STARTED_ASC   = 2;
  const ORDER_STARTED_DESC  = 3;
  const ORDER_ENDED_ASC     = 4;
  const ORDER_ENDED_DESC    = 5;

  const ENDED_YES = 0;
  const ENDED_NO  = 1;
  const ENDED_ALL = 2;

  private $ids;
  private $userPHIDs;
  private $objectPHIDs;
  private $ended = self::ENDED_ALL;

  private $needPreemptingEvents;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withEnded($ended) {
    $this->ended = $ended;
    return $this;
  }

  public function setOrder($order) {
    switch ($order) {
      case self::ORDER_ID_ASC:
        $this->setOrderVector(array('-id'));
        break;
      case self::ORDER_ID_DESC:
        $this->setOrderVector(array('id'));
        break;
      case self::ORDER_STARTED_ASC:
        $this->setOrderVector(array('-start', '-id'));
        break;
      case self::ORDER_STARTED_DESC:
        $this->setOrderVector(array('start', 'id'));
        break;
      case self::ORDER_ENDED_ASC:
        $this->setOrderVector(array('-end', '-id'));
        break;
      case self::ORDER_ENDED_DESC:
        $this->setOrderVector(array('end', 'id'));
        break;
      default:
        throw new Exception(pht('Unknown order "%s".', $order));
    }

    return $this;
  }

  public function needPreemptingEvents($need_events) {
    $this->needPreemptingEvents = $need_events;
    return $this;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    switch ($this->ended) {
      case self::ENDED_ALL:
        break;
      case self::ENDED_YES:
        $where[] = qsprintf(
          $conn,
          'dateEnded IS NOT NULL');
        break;
      case self::ENDED_NO:
        $where[] = qsprintf(
          $conn,
          'dateEnded IS NULL');
        break;
      default:
        throw new Exception("Unknown ended '{$this->ended}'!");
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'start' => array(
        'column' => 'dateStarted',
        'type' => 'int',
      ),
      'end' => array(
        'column' => 'dateEnded',
        'type' => 'int',
        'null' => 'head',
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $usertime = $this->loadCursorObject($cursor);
    return array(
      'id' => $usertime->getID(),
      'start' => $usertime->getDateStarted(),
      'end' => $usertime->getDateEnded(),
    );
  }

  protected function loadPage() {
    $usertime = new PhrequentUserTime();
    $conn = $usertime->establishConnection('r');

    $data = queryfx_all(
      $conn,
      'SELECT usertime.* FROM %T usertime %Q %Q %Q',
      $usertime->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $usertime->loadAllFromArray($data);
  }

  protected function didFilterPage(array $page) {
    if ($this->needPreemptingEvents) {
      $usertime = new PhrequentUserTime();
      $conn_r = $usertime->establishConnection('r');

      $preempt = array();
      foreach ($page as $event) {
        $preempt[] = qsprintf(
          $conn_r,
          '(userPHID = %s AND
            (dateStarted BETWEEN %d AND %d) AND
            (dateEnded IS NULL OR dateEnded > %d))',
          $event->getUserPHID(),
          $event->getDateStarted(),
          nonempty($event->getDateEnded(), PhabricatorTime::getNow()),
          $event->getDateStarted());
      }

      $preempting_events = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE %Q ORDER BY dateStarted ASC, id ASC',
        $usertime->getTableName(),
        implode(' OR ', $preempt));
      $preempting_events = $usertime->loadAllFromArray($preempting_events);

      $preempting_events = mgroup($preempting_events, 'getUserPHID');

      foreach ($page as $event) {
        $e_start = $event->getDateStarted();
        $e_end = $event->getDateEnded();

        $select = array();
        $user_events = idx($preempting_events, $event->getUserPHID(), array());
        foreach ($user_events as $u_event) {
          if ($u_event->getID() == $event->getID()) {
            // Don't allow an event to preempt itself.
            continue;
          }

          $u_start = $u_event->getDateStarted();
          $u_end = $u_event->getDateEnded();

          if ($u_start < $e_start) {
            // This event started before our event started, so it's not
            // preempting us.
            continue;
          }

          if ($u_start == $e_start) {
            if ($u_event->getID() < $event->getID()) {
              // This event started at the same time as our event started,
              // but has a lower ID, so it's not preempting us.
              continue;
            }
          }

          if (($e_end !== null) && ($u_start > $e_end)) {
            // Our event has ended, and this event started after it ended.
            continue;
          }

          if (($u_end !== null) && ($u_end < $e_start)) {
            // This event ended before our event began.
            continue;
          }

          $select[] = $u_event;
        }

        $event->attachPreemptingEvents($select);
      }
    }

    return $page;
  }

/* -(  Helper Functions ) --------------------------------------------------- */

  public static function getEndedSearchOptions() {
    return array(
      self::ENDED_ALL => pht('All'),
      self::ENDED_NO  => pht('No'),
      self::ENDED_YES => pht('Yes'),
    );
  }

  public static function getOrderSearchOptions() {
    return array(
      self::ORDER_STARTED_ASC   => pht('by furthest start date'),
      self::ORDER_STARTED_DESC  => pht('by nearest start date'),
      self::ORDER_ENDED_ASC     => pht('by furthest end date'),
      self::ORDER_ENDED_DESC    => pht('by nearest end date'),
    );
  }

  public static function getUserTotalObjectsTracked(
    PhabricatorUser $user,
    $limit = PHP_INT_MAX) {

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    $count = queryfx_one(
      $conn,
      'SELECT COUNT(usertime.id) N FROM %T usertime '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.dateEnded IS NULL '.
      'LIMIT %d',
      $usertime_dao->getTableName(),
      $user->getPHID(),
      $limit);
    return $count['N'];
  }

  public static function isUserTrackingObject(
    PhabricatorUser $user,
    $phid) {

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    $count = queryfx_one(
      $conn,
      'SELECT COUNT(usertime.id) N FROM %T usertime '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NULL',
      $usertime_dao->getTableName(),
      $user->getPHID(),
      $phid);
    return $count['N'] > 0;
  }

  public static function getUserTimeSpentOnObject(
    PhabricatorUser $user,
    $phid) {

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    // First calculate all the time spent where the
    // usertime blocks have ended.
    $sum_ended = queryfx_one(
      $conn,
      'SELECT SUM(usertime.dateEnded - usertime.dateStarted) N '.
      'FROM %T usertime '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NOT NULL',
      $usertime_dao->getTableName(),
      $user->getPHID(),
      $phid);

    // Now calculate the time spent where the usertime
    // blocks have not yet ended.
    $sum_not_ended = queryfx_one(
      $conn,
      'SELECT SUM(UNIX_TIMESTAMP() - usertime.dateStarted) N '.
      'FROM %T usertime '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NULL',
      $usertime_dao->getTableName(),
      $user->getPHID(),
      $phid);

    return $sum_ended['N'] + $sum_not_ended['N'];
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhrequentApplication';
  }

}
