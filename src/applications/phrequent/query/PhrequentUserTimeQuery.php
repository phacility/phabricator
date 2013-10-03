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

  public static function loadUserStack(PhabricatorUser $user) {
    if (!$user->isLoggedIn()) {
      return array();
    }

    return id(new PhrequentUserTime())->loadAllWhere(
      'userPHID = %s AND dateEnded IS NULL
        ORDER BY dateStarted DESC, id DESC',
      $user->getPHID());
  }

  public static function getTotalTimeSpentOnObject($phid) {
    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    // First calculate all the time spent where the
    // usertime blocks have ended.
    $sum_ended = queryfx_one(
      $conn,
      'SELECT SUM(usertime.dateEnded - usertime.dateStarted) N '.
      'FROM %T usertime '.
      'WHERE usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NOT NULL',
      $usertime_dao->getTableName(),
      $phid);

    // Now calculate the time spent where the usertime
    // blocks have not yet ended.
    $sum_not_ended = queryfx_one(
      $conn,
      'SELECT SUM(UNIX_TIMESTAMP() - usertime.dateStarted) N '.
      'FROM %T usertime '.
      'WHERE usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NULL',
      $usertime_dao->getTableName(),
      $phid);

    return $sum_ended['N'] + $sum_not_ended['N'];
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

}
