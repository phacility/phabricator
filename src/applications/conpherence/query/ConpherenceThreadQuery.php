<?php

final class ConpherenceThreadQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const TRANSACTION_LIMIT = 100;

  private $phids;
  private $ids;
  private $participantPHIDs;
  private $needParticipants;
  private $needCropPics;
  private $needOrigPics;
  private $needTransactions;
  private $needParticipantCache;
  private $afterTransactionID;
  private $beforeTransactionID;
  private $transactionLimit;
  private $fulltext;

  public function needParticipantCache($participant_cache) {
    $this->needParticipantCache = $participant_cache;
    return $this;
  }

  public function needParticipants($need) {
    $this->needParticipants = $need;
    return $this;
  }

  public function needCropPics($need) {
    $this->needCropPics = $need;
    return $this;
  }

  public function needOrigPics($need_widget_data) {
    $this->needOrigPics = $need_widget_data;
    return $this;
  }

  public function needTransactions($need_transactions) {
    $this->needTransactions = $need_transactions;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withParticipantPHIDs(array $phids) {
    $this->participantPHIDs = $phids;
    return $this;
  }

  public function setAfterTransactionID($id) {
    $this->afterTransactionID = $id;
    return $this;
  }

  public function setBeforeTransactionID($id) {
    $this->beforeTransactionID = $id;
    return $this;
  }

  public function setTransactionLimit($transaction_limit) {
    $this->transactionLimit = $transaction_limit;
    return $this;
  }

  public function getTransactionLimit() {
    return $this->transactionLimit;
  }

  public function withFulltext($query) {
    $this->fulltext = $query;
    return $this;
  }

  protected function loadPage() {
    $table = new ConpherenceThread();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT thread.* FROM %T thread %Q %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $conpherences = $table->loadAllFromArray($data);

    if ($conpherences) {
      $conpherences = mpull($conpherences, null, 'getPHID');
      $this->loadParticipantsAndInitHandles($conpherences);
      if ($this->needParticipantCache) {
        $this->loadCoreHandles($conpherences, 'getRecentParticipantPHIDs');
      }
      if ($this->needParticipants) {
        $this->loadCoreHandles($conpherences, 'getParticipantPHIDs');
      }
      if ($this->needTransactions) {
        $this->loadTransactionsAndHandles($conpherences);
      }
      if ($this->needOrigPics || $this->needCropPics) {
        $this->initImages($conpherences);
      }
      if ($this->needOrigPics) {
        $this->loadOrigPics($conpherences);
      }
      if ($this->needCropPics) {
        $this->loadCropPics($conpherences);
      }
    }

    return $conpherences;
  }

  protected function buildGroupClause(AphrontDatabaseConnection $conn_r) {
    if ($this->participantPHIDs !== null || strlen($this->fulltext)) {
      return 'GROUP BY thread.id';
    } else {
      return $this->buildApplicationSearchGroupClause($conn_r);
    }
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->participantPHIDs !== null) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T p ON p.conpherencePHID = thread.phid',
        id(new ConpherenceParticipant())->getTableName());
    }

    if (strlen($this->fulltext)) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T idx ON idx.threadPHID = thread.phid',
        id(new ConpherenceIndex())->getTableName());
    }

    $joins[] = $this->buildApplicationSearchJoinClause($conn_r);
    return implode(' ', $joins);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'thread.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'thread.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->participantPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'p.participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    if (strlen($this->fulltext)) {
      $where[] = qsprintf(
        $conn_r,
        'MATCH(idx.corpus) AGAINST (%s IN BOOLEAN MODE)',
        $this->fulltext);
    }

    return $this->formatWhereClause($where);
  }

  private function loadParticipantsAndInitHandles(array $conpherences) {
    $participants = id(new ConpherenceParticipant())
      ->loadAllWhere('conpherencePHID IN (%Ls)', array_keys($conpherences));
    $map = mgroup($participants, 'getConpherencePHID');

    foreach ($conpherences as $current_conpherence) {
      $conpherence_phid = $current_conpherence->getPHID();

      $conpherence_participants = idx(
        $map,
        $conpherence_phid,
        array());

      $conpherence_participants = mpull(
        $conpherence_participants,
        null,
        'getParticipantPHID');

      $current_conpherence->attachParticipants($conpherence_participants);
      $current_conpherence->attachHandles(array());
    }

    return $this;
  }

  private function loadCoreHandles(
    array $conpherences,
    $method) {

    $handle_phids = array();
    foreach ($conpherences as $conpherence) {
      $handle_phids[$conpherence->getPHID()] =
        $conpherence->$method();
    }
    $flat_phids = array_mergev($handle_phids);
    $viewer = $this->getViewer();
    $handles = $viewer->loadHandles($flat_phids);
    $handles = iterator_to_array($handles);
    foreach ($handle_phids as $conpherence_phid => $phids) {
      $conpherence = $conpherences[$conpherence_phid];
      $conpherence->attachHandles(
        $conpherence->getHandles() + array_select_keys($handles, $phids));
    }
    return $this;
  }

  private function loadTransactionsAndHandles(array $conpherences) {
    $query = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array_keys($conpherences))
      ->needHandles(true);

    // We have to flip these for the underyling query class. The semantics of
    // paging are tricky business.
    if ($this->afterTransactionID) {
      $query->setBeforeID($this->afterTransactionID);
    } else if ($this->beforeTransactionID) {
      $query->setAfterID($this->beforeTransactionID);
    }
    if ($this->getTransactionLimit()) {
      // fetch an extra for "show older" scenarios
      $query->setLimit($this->getTransactionLimit() + 1);
    }
    $transactions = $query->execute();
    $transactions = mgroup($transactions, 'getObjectPHID');
    foreach ($conpherences as $phid => $conpherence) {
      $current_transactions = idx($transactions, $phid, array());
      $handles = array();
      foreach ($current_transactions as $transaction) {
        $handles += $transaction->getHandles();
      }
      $conpherence->attachHandles($conpherence->getHandles() + $handles);
      $conpherence->attachTransactions($current_transactions);
    }
    return $this;
  }

  private function loadOrigPics(array $conpherences) {
    return $this->loadPics(
      $conpherences,
      ConpherenceImageData::SIZE_ORIG);
  }

  private function loadCropPics(array $conpherences) {
    return $this->loadPics(
      $conpherences,
      ConpherenceImageData::SIZE_CROP);
  }

  private function initImages($conpherences) {
    foreach ($conpherences as $conpherence) {
      $conpherence->attachImages(array());
    }
  }

  private function loadPics(array $conpherences, $size) {
    $conpherence_pic_phids = array();
    foreach ($conpherences as $conpherence) {
      $phid = $conpherence->getImagePHID($size);
      if ($phid) {
        $conpherence_pic_phids[$conpherence->getPHID()] = $phid;
      }
    }

    if (!$conpherence_pic_phids) {
      return $this;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($conpherence_pic_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    foreach ($conpherence_pic_phids as $conpherence_phid => $pic_phid) {
      $conpherences[$conpherence_phid]->setImage($files[$pic_phid], $size);
    }

    return $this;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConpherenceApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'thread';
  }

}
