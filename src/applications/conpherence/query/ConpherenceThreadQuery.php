<?php

final class ConpherenceThreadQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const TRANSACTION_LIMIT = 100;

  private $phids;
  private $ids;
  private $participantPHIDs;
  private $needParticipants;
  private $needTransactions;
  private $afterTransactionID;
  private $beforeTransactionID;
  private $transactionLimit;
  private $fulltext;
  private $needProfileImage;

  public function needParticipants($need) {
    $this->needParticipants = $need;
    return $this;
  }

  public function needProfileImage($need) {
    $this->needProfileImage = $need;
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

  public function withTitleNgrams($ngrams) {
    return $this->withNgramsConstraint(
      id(new ConpherenceThreadTitleNgrams()),
      $ngrams);
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
      if ($this->needParticipants) {
        $this->loadCoreHandles($conpherences, 'getParticipantPHIDs');
      }
      if ($this->needTransactions) {
        $this->loadTransactionsAndHandles($conpherences);
      }
      if ($this->needProfileImage) {
        $default = null;
        $file_phids = mpull($conpherences, 'getProfileImagePHID');
        $file_phids = array_filter($file_phids);
        if ($file_phids) {
          $files = id(new PhabricatorFileQuery())
            ->setParentQuery($this)
            ->setViewer($this->getViewer())
            ->withPHIDs($file_phids)
            ->execute();
          $files = mpull($files, null, 'getPHID');
        } else {
          $files = array();
        }

        foreach ($conpherences as $conpherence) {
          $file = idx($files, $conpherence->getProfileImagePHID());
          if (!$file) {
            if (!$default) {
              $default = PhabricatorFile::loadBuiltin(
                $this->getViewer(),
                'conpherence.png');
            }
            $file = $default;
          }
          $conpherence->attachProfileImageFile($file);
        }
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

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->participantPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T p ON p.conpherencePHID = thread.phid',
        id(new ConpherenceParticipant())->getTableName());
    }

    if (strlen($this->fulltext)) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T idx ON idx.threadPHID = thread.phid',
        id(new ConpherenceIndex())->getTableName());
    }

    // See note in buildWhereClauseParts() about this optimization.
    $viewer = $this->getViewer();
    if (!$viewer->isOmnipotent() && $viewer->isLoggedIn()) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T vp ON vp.conpherencePHID = thread.phid
          AND vp.participantPHID = %s',
        id(new ConpherenceParticipant())->getTableName(),
        $viewer->getPHID());
    }

    return $joins;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    // Optimize policy filtering of private rooms. If we are not looking for
    // particular rooms by ID or PHID, we can just skip over any rooms with
    // "View Policy: Room Participants" if the viewer isn't a participant: we
    // know they won't be able to see the room.
    // This avoids overheating browse/search queries, since it's common for
    // a large number of rooms to be private and have this view policy.
    $viewer = $this->getViewer();

    $can_optimize =
      !$viewer->isOmnipotent() &&
      ($this->ids === null) &&
      ($this->phids === null);

    if ($can_optimize) {
      $members_policy = id(new ConpherenceThreadMembersPolicyRule())
        ->getObjectPolicyFullKey();

      if ($viewer->isLoggedIn()) {
        $where[] = qsprintf(
          $conn,
          'thread.viewPolicy != %s OR vp.participantPHID = %s',
          $members_policy,
          $viewer->getPHID());
      } else {
        $where[] = qsprintf(
          $conn,
          'thread.viewPolicy != %s',
          $members_policy);
      }
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'thread.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'thread.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->participantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'p.participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    if (strlen($this->fulltext)) {
      $where[] = qsprintf(
        $conn,
        'MATCH(idx.corpus) AGAINST (%s IN BOOLEAN MODE)',
        $this->fulltext);
    }

    return $where;
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

    // We have to flip these for the underlying query class. The semantics of
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

  public function getQueryApplicationClass() {
    return 'PhabricatorConpherenceApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'thread';
  }

}
