<?php

/**
 * @group conpherence
 */
final class ConpherenceThreadQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const TRANSACTION_LIMIT = 100;

  private $phids;
  private $ids;
  private $needWidgetData;
  private $needTransactions;
  private $needParticipantCache;
  private $needFilePHIDs;
  private $afterTransactionID;
  private $beforeTransactionID;
  private $transactionLimit;

  public function needFilePHIDs($need_file_phids) {
    $this->needFilePHIDs = $need_file_phids;
    return $this;
  }

  public function needParticipantCache($participant_cache) {
    $this->needParticipantCache = $participant_cache;
    return $this;
  }

  public function needWidgetData($need_widget_data) {
    $this->needWidgetData = $need_widget_data;
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

  protected function loadPage() {
    $table = new ConpherenceThread();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT conpherence_thread.* FROM %T conpherence_thread %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $conpherences = $table->loadAllFromArray($data);

    if ($conpherences) {
      $conpherences = mpull($conpherences, null, 'getPHID');
      $this->loadParticipantsAndInitHandles($conpherences);
      if ($this->needParticipantCache) {
        $this->loadCoreHandles($conpherences, 'getRecentParticipantPHIDs');
      } else if ($this->needWidgetData) {
        $this->loadCoreHandles($conpherences, 'getParticipantPHIDs');
      }
      if ($this->needTransactions) {
        $this->loadTransactionsAndHandles($conpherences);
      }
      if ($this->needFilePHIDs || $this->needWidgetData) {
        $this->loadFilePHIDs($conpherences);
      }
      if ($this->needWidgetData) {
        $this->loadWidgetData($conpherences);
      }
    }

    return $conpherences;
  }

  protected function buildWhereClause($conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    return $this->formatWhereClause($where);
  }

  private function loadParticipantsAndInitHandles(array $conpherences) {
    $participants = id(new ConpherenceParticipant())
      ->loadAllWhere('conpherencePHID IN (%Ls)', array_keys($conpherences));
    $map = mgroup($participants, 'getConpherencePHID');
    foreach ($map as $conpherence_phid => $conpherence_participants) {
      $current_conpherence = $conpherences[$conpherence_phid];
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
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($flat_phids)
      ->execute();
    foreach ($handle_phids as $conpherence_phid => $phids) {
      $conpherence = $conpherences[$conpherence_phid];
      $conpherence->attachHandles(array_select_keys($handles, $phids));
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
      $current_transactions = $transactions[$phid];
      $handles = array();
      foreach ($current_transactions as $transaction) {
        $handles += $transaction->getHandles();
      }
      $conpherence->attachHandles($conpherence->getHandles() + $handles);
      $conpherence->attachTransactions($transactions[$phid]);
    }
    return $this;
  }

  private function loadFilePHIDs(array $conpherences) {
    $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_FILE;
    $file_edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array_keys($conpherences))
      ->withEdgeTypes(array($edge_type))
      ->execute();
    foreach ($file_edges as $conpherence_phid => $data) {
      $conpherence = $conpherences[$conpherence_phid];
      $conpherence->attachFilePHIDs(array_keys($data[$edge_type]));
    }
    return $this;
  }

  private function loadWidgetData(array $conpherences) {
    $participant_phids = array();
    $file_phids = array();
    foreach ($conpherences as $conpherence) {
      $participant_phids[] = array_keys($conpherence->getParticipants());
      $file_phids[] = $conpherence->getFilePHIDs();
    }
    $participant_phids = array_mergev($participant_phids);
    $file_phids = array_mergev($file_phids);

    $epochs = ConpherenceTimeUtil::getCalendarEventEpochs(
      $this->getViewer());
    $start_epoch = $epochs['start_epoch'];
    $end_epoch = $epochs['end_epoch'];
    $statuses = id(new PhabricatorUserStatus())
      ->loadAllWhere(
        'userPHID in (%Ls) AND dateTo >= %d AND dateFrom <= %d',
        $participant_phids,
        $start_epoch,
        $end_epoch);
    $statuses = mgroup($statuses, 'getUserPHID');

    // attached files
    $files = array();
    $file_author_phids = array();
    $authors = array();
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
      $file_author_phids = mpull($files, 'getAuthorPHID', 'getPHID');
      $authors = id(new PhabricatorHandleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($file_author_phids)
        ->execute();
      $authors = mpull($authors, null, 'getPHID');
    }

    foreach ($conpherences as $phid => $conpherence) {
      $participant_phids = array_keys($conpherence->getParticipants());
      $statuses = array_select_keys($statuses, $participant_phids);
      $statuses = array_mergev($statuses);
      $statuses = msort($statuses, 'getDateFrom');

      $conpherence_files = array();
      $files_authors = array();
      foreach ($conpherence->getFilePHIDs() as $curr_phid) {
        $curr_file = idx($files, $curr_phid);
        if (!$curr_file) {
          // this file was deleted or user doesn't have permission to see it
          // this is generally weird
          continue;
        }
        $conpherence_files[$curr_phid] = $curr_file;
        // some files don't have authors so be careful
        $current_author = null;
        $current_author_phid = idx($file_author_phids, $curr_phid);
        if ($current_author_phid) {
          $current_author = $authors[$current_author_phid];
        }
        $files_authors[$curr_phid] = $current_author;
      }
      $widget_data = array(
        'statuses' => $statuses,
        'files' => $conpherence_files,
        'files_authors' => $files_authors
      );
      $conpherence->attachWidgetData($widget_data);
    }

    return $this;
  }

}
