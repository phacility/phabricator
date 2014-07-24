<?php

final class PhrequentUserTimeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const ORDER_ID_ASC        = 0;
  const ORDER_ID_DESC       = 1;
  const ORDER_STARTED_ASC   = 2;
  const ORDER_STARTED_DESC  = 3;
  const ORDER_ENDED_ASC     = 4;
  const ORDER_ENDED_DESC    = 5;
  const ORDER_DURATION_ASC  = 6;
  const ORDER_DURATION_DESC = 7;

  const ENDED_YES = 0;
  const ENDED_NO  = 1;
  const ENDED_ALL = 2;

  private $userPHIDs;
  private $objectPHIDs;
  private $order = self::ORDER_ID_ASC;
  private $ended = self::ENDED_ALL;

  private $needPreemptingEvents;

  public function withUserPHIDs($user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withObjectPHIDs($object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withEnded($ended) {
    $this->ended = $ended;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function needPreemptingEvents($need_events) {
    $this->needPreemptingEvents = $need_events;
    return $this;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->userPHIDs) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->objectPHIDs) {
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

  protected function getPagingColumn() {
    switch ($this->order) {
      case self::ORDER_ID_ASC:
      case self::ORDER_ID_DESC:
        return 'id';
      case self::ORDER_STARTED_ASC:
      case self::ORDER_STARTED_DESC:
        return 'dateStarted';
      case self::ORDER_ENDED_ASC:
      case self::ORDER_ENDED_DESC:
        return 'dateEnded';
      case self::ORDER_DURATION_ASC:
      case self::ORDER_DURATION_DESC:
        return 'COALESCE(dateEnded, UNIX_TIMESTAMP()) - dateStarted';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  protected function getPagingValue($result) {
    switch ($this->order) {
      case self::ORDER_ID_ASC:
      case self::ORDER_ID_DESC:
        return $result->getID();
      case self::ORDER_STARTED_ASC:
      case self::ORDER_STARTED_DESC:
        return $result->getDateStarted();
      case self::ORDER_ENDED_ASC:
      case self::ORDER_ENDED_DESC:
        return $result->getDateEnded();
      case self::ORDER_DURATION_ASC:
      case self::ORDER_DURATION_DESC:
        return ($result->getDateEnded() || time()) - $result->getDateStarted();
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  protected function getReversePaging() {
    switch ($this->order) {
      case self::ORDER_ID_ASC:
      case self::ORDER_STARTED_ASC:
      case self::ORDER_ENDED_ASC:
      case self::ORDER_DURATION_ASC:
        return true;
      case self::ORDER_ID_DESC:
      case self::ORDER_STARTED_DESC:
      case self::ORDER_ENDED_DESC:
      case self::ORDER_DURATION_DESC:
        return false;
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
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

          if (($u_start >= $e_start) &&
              ($u_end === null || $u_end > $e_start)) {
            $select[] = $u_event;
          }
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
      self::ENDED_YES => pht('Yes'));
  }

  public static function getOrderSearchOptions() {
    return array(
      self::ORDER_STARTED_ASC   => pht('by furthest start date'),
      self::ORDER_STARTED_DESC  => pht('by nearest start date'),
      self::ORDER_ENDED_ASC     => pht('by furthest end date'),
      self::ORDER_ENDED_DESC    => pht('by nearest end date'),
      self::ORDER_DURATION_ASC  => pht('by smallest duration'),
      self::ORDER_DURATION_DESC => pht('by largest duration'));
  }

  public static function getUserTotalObjectsTracked(
    PhabricatorUser $user) {

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    $count = queryfx_one(
      $conn,
      'SELECT COUNT(usertime.id) N FROM %T usertime '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.dateEnded IS NULL',
      $usertime_dao->getTableName(),
      $user->getPHID());
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
