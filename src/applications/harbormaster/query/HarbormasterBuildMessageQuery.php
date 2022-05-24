<?php

final class HarbormasterBuildMessageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $receiverPHIDs;
  private $consumed;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withReceiverPHIDs(array $phids) {
    $this->receiverPHIDs = $phids;
    return $this;
  }

  public function withConsumed($consumed) {
    $this->consumed = $consumed;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuildMessage();
  }

  protected function willFilterPage(array $page) {
    $receiver_phids = array_filter(mpull($page, 'getReceiverPHID'));
    if ($receiver_phids) {
      $receivers = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($receiver_phids)
        ->setParentQuery($this)
        ->execute();
      $receivers = mpull($receivers, null, 'getPHID');
    } else {
      $receivers = array();
    }

    foreach ($page as $key => $message) {
      $receiver_phid = $message->getReceiverPHID();

      if (empty($receivers[$receiver_phid])) {
        unset($page[$key]);
        $this->didRejectResult($message);
        continue;
      }

      $message->attachReceiver($receivers[$receiver_phid]);
    }

    return $page;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->receiverPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'receiverPHID IN (%Ls)',
        $this->receiverPHIDs);
    }

    if ($this->consumed !== null) {
      $where[] = qsprintf(
        $conn,
        'isConsumed = %d',
        (int)$this->consumed);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
