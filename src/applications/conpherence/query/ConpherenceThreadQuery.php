<?php

/**
 * @group conpherence
 */
final class ConpherenceThreadQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $ids;
  private $needWidgetData;
  private $needHeaderPics;
  private $needOrigPics;

  public function needOrigPics($need_orig_pics) {
    $this->needOrigPics = $need_orig_pics;
    return $this;
  }

  public function needHeaderPics($need_header_pics) {
    $this->needHeaderPics = $need_header_pics;
    return $this;
  }

  public function needWidgetData($need_widget_data) {
    $this->needWidgetData = $need_widget_data;
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
      $this->loadParticipants($conpherences);
      $this->loadTransactionsAndHandles($conpherences);
      $this->loadFilePHIDs($conpherences);
      if ($this->needWidgetData) {
        $this->loadWidgetData($conpherences);
      }
      if ($this->needOrigPics) {
        $this->loadOrigPics($conpherences);
      }
      if ($this->needHeaderPics) {
        $this->loadHeaderPics($conpherences);
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

  private function loadParticipants(array $conpherences) {
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
    }

    return $this;
  }

  private function loadTransactionsAndHandles(array $conpherences) {
    $transactions = id(new ConpherenceTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array_keys($conpherences))
      ->needHandles(true)
      ->execute();
    $transactions = mgroup($transactions, 'getObjectPHID');
    foreach ($conpherences as $phid => $conpherence) {
      $current_transactions = $transactions[$phid];
      $handles = array();
      foreach ($current_transactions as $transaction) {
        $handles += $transaction->getHandles();
      }
      $conpherence->attachHandles($handles);
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

    // statuses of everyone currently in the conpherence
    // for a rolling one week window
    $start_of_week = phabricator_format_local_time(
      strtotime('today'),
      $this->getViewer(),
      'U');
    $end_of_week = phabricator_format_local_time(
      strtotime('midnight +1 week'),
      $this->getViewer(),
      'U');
    $statuses = id(new PhabricatorUserStatus())
      ->loadAllWhere(
        'userPHID in (%Ls) AND dateTo >= %d AND dateFrom <= %d',
        $participant_phids,
        $start_of_week,
        $end_of_week);
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
      $authors = id(new PhabricatorObjectHandleData($file_author_phids))
        ->setViewer($this->getViewer())
        ->loadHandles();
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
        $conpherence_files[$curr_phid] = $files[$curr_phid];
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

  private function loadOrigPics(array $conpherences) {
    return $this->loadPics(
      $conpherences,
      ConpherenceImageData::SIZE_ORIG);
  }

  private function loadHeaderPics(array $conpherences) {
    return $this->loadPics(
      $conpherences,
      ConpherenceImageData::SIZE_HEAD);
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

}
