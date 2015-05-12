<?php

final class ConpherenceThreadQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const TRANSACTION_LIMIT = 100;

  private $phids;
  private $ids;
  private $participantPHIDs;
  private $isRoom;
  private $needParticipants;
  private $needWidgetData;
  private $needCropPics;
  private $needOrigPics;
  private $needTransactions;
  private $needParticipantCache;
  private $needFilePHIDs;
  private $afterTransactionID;
  private $beforeTransactionID;
  private $transactionLimit;
  private $fulltext;

  public function needFilePHIDs($need_file_phids) {
    $this->needFilePHIDs = $need_file_phids;
    return $this;
  }

  public function needParticipantCache($participant_cache) {
    $this->needParticipantCache = $participant_cache;
    return $this;
  }

  public function needParticipants($need) {
    $this->needParticipants = $need;
    return $this;
  }

  public function needWidgetData($need_widget_data) {
    $this->needWidgetData = $need_widget_data;
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

  public function withIsRoom($bool) {
    $this->isRoom = $bool;
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
      'SELECT conpherence_thread.* FROM %T conpherence_thread %Q %Q %Q %Q %Q',
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
      if ($this->needWidgetData || $this->needParticipants) {
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
      return 'GROUP BY conpherence_thread.id';
    } else {
      return $this->buildApplicationSearchGroupClause($conn_r);
    }
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->participantPHIDs !== null) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T p ON p.conpherencePHID = conpherence_thread.phid',
        id(new ConpherenceParticipant())->getTableName());
    }

    $viewer = $this->getViewer();
    if ($this->shouldJoinForViewer($viewer)) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T v ON v.conpherencePHID = conpherence_thread.phid '.
        'AND v.participantPHID = %s',
        id(new ConpherenceParticipant())->getTableName(),
        $viewer->getPHID());
    }

    if (strlen($this->fulltext)) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T idx ON idx.threadPHID = conpherence_thread.phid',
        id(new ConpherenceIndex())->getTableName());
    }

    $joins[] = $this->buildApplicationSearchJoinClause($conn_r);
    return implode(' ', $joins);
  }

  private function shouldJoinForViewer(PhabricatorUser $viewer) {
    if ($viewer->isLoggedIn() &&
      $this->ids === null &&
      $this->phids === null) {
      return true;
    }
    return false;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'conpherence_thread.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'conpherence_thread.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->participantPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'p.participantPHID IN (%Ls)',
        $this->participantPHIDs);
    }

    if ($this->isRoom !== null) {
      $where[] = qsprintf(
        $conn_r,
        'conpherence_thread.isRoom = %d',
        (int)$this->isRoom);
    }

    if (strlen($this->fulltext)) {
      $where[] = qsprintf(
        $conn_r,
        'MATCH(idx.corpus) AGAINST (%s IN BOOLEAN MODE)',
        $this->fulltext);
    }

    $viewer = $this->getViewer();
    if ($this->shouldJoinForViewer($viewer)) {
      $where[] = qsprintf(
        $conn_r,
        'conpherence_thread.isRoom = 1 OR v.participantPHID IS NOT NULL');
    } else if ($this->phids === null && $this->ids === null) {
      $where[] = qsprintf(
        $conn_r,
        'conpherence_thread.isRoom = 1');
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

  private function loadFilePHIDs(array $conpherences) {
    $edge_type = PhabricatorObjectHasFileEdgeType::EDGECONST;
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

    $epochs = CalendarTimeUtil::getCalendarEventEpochs(
      $this->getViewer());
    $start_epoch = $epochs['start_epoch'];
    $end_epoch = $epochs['end_epoch'];
    $statuses = id(new PhabricatorCalendarEventQuery())
      ->setViewer($this->getViewer())
      ->withInvitedPHIDs($participant_phids)
      ->withDateRange($start_epoch, $end_epoch)
      ->execute();

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
        'files_authors' => $files_authors,
      );
      $conpherence->attachWidgetData($widget_data);
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

}
