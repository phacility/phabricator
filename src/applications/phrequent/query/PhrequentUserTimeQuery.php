<?php

final class PhrequentUserTimeQuery extends PhabricatorOffsetPagedQuery {

  const ORDER_ID       = 'order-id';
  const ORDER_STARTED  = 'order-started';
  const ORDER_ENDED    = 'order-ended';
  const ORDER_DURATION = 'order-duration';

  const ENDED_YES = "ended-yes";
  const ENDED_NO  = "ended-no";
  const ENDED_ALL = "ended-all";

  private $userPHIDs;
  private $objectPHIDs;
  private $order = self::ORDER_ID;
  private $ended = self::ENDED_ALL;

  public function setUsers($user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function setObjects($object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function setEnded($ended) {
    $this->ended = $ended;
    return $this;
  }

  public function execute() {
    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    $data = queryfx_all(
      $conn,
      'SELECT usertime.* FROM %T usertime %Q %Q %Q',
      $usertime_dao->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $usertime_dao->loadAllFromArray($data);
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

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn) {
    switch ($this->order) {
      case self::ORDER_ID:
        return 'ORDER BY id ASC';
      case self::ORDER_STARTED:
        return 'ORDER BY dateStarted DESC';
      case self::ORDER_ENDED:
        return 'ORDER BY dateEnded IS NULL, dateEnded DESC, dateStarted DESC';
      case self::ORDER_DURATION:
        return 'ORDER BY COALESCE(dateEnded, UNIX_TIMESTAMP()) - dateStarted '.
               'DESC';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

/* -(  Helper Functions ) --------------------------------------------------- */

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
