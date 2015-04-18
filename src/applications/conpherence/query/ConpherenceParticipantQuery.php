<?php

/**
 * Query class that answers these questions:
 *
 * - Q: What are the conpherences to show when I land on /conpherence/ ?
 * - A:
 *
 *     id(new ConpherenceParticipantQuery())
 *     ->withParticipantPHIDs(array($my_phid))
 *     ->execute();
 *
 * - Q: What are the next set of conpherences as I scroll up (more recent) or
 *      down (less recent) this list of conpherences?
 * - A:
 *
 *     id(new ConpherenceParticipantQuery())
 *     ->withParticipantPHIDs(array($my_phid))
 *     ->withParticipantCursor($top_participant)
 *     ->setOrder(ConpherenceParticipantQuery::ORDER_NEWER)
 *     ->execute();
 *
 *     -or-
 *
 *     id(new ConpherenceParticipantQuery())
 *     ->withParticipantPHIDs(array($my_phid))
 *     ->withParticipantCursor($bottom_participant)
 *     ->setOrder(ConpherenceParticipantQuery::ORDER_OLDER)
 *     ->execute();
 *
 * For counts of read, un-read, or all conpherences by participant, see
 * @{class:ConpherenceParticipantCountQuery}.
 */
final class ConpherenceParticipantQuery extends PhabricatorOffsetPagedQuery {

  const LIMIT = 100;
  const ORDER_NEWER = 'newer';
  const ORDER_OLDER = 'older';

  private $participantPHIDs;
  private $participantCursor;
  private $order = self::ORDER_OLDER;

  public function withParticipantPHIDs(array $phids) {
    $this->participantPHIDs = $phids;
    return $this;
  }

  public function withParticipantCursor(ConpherenceParticipant $participant) {
    $this->participantCursor = $participant;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function execute() {
    $table = new ConpherenceParticipant();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T participant %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $participants = $table->loadAllFromArray($data);

    $participants = mpull($participants, null, 'getConpherencePHID');

    if ($this->order == self::ORDER_NEWER) {
      $participants = array_reverse($participants);
    }

    return $participants;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->participantPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    if ($this->participantCursor) {
      $date_touched = $this->participantCursor->getDateTouched();
      $id = $this->participantCursor->getID();
      if ($this->order == self::ORDER_OLDER) {
        $compare_date = '<';
        $compare_id = '<=';
      } else {
        $compare_date = '>';
        $compare_id = '>=';
      }
      $where[] = qsprintf(
        $conn_r,
        '(dateTouched %Q %d OR (dateTouched = %d AND id %Q %d))',
        $compare_date,
        $date_touched,
        $date_touched,
        $compare_id,
        $id);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    $order_word = ($this->order == self::ORDER_OLDER) ? 'DESC' : 'ASC';
    // if these are different direction we won't get as efficient a query
    // see http://dev.mysql.com/doc/refman/5.5/en/order-by-optimization.html
    $order = qsprintf(
      $conn_r,
      'ORDER BY dateTouched %Q, id %Q',
      $order_word,
      $order_word);

    return $order;
  }

}
