<?php

final class HeraldTranscriptQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $needPartialRecords;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function needPartialRecords($need_partial) {
    $this->needPartialRecords = $need_partial;
    return $this;
  }

  public function loadPage() {
    $transcript = new HeraldTranscript();
    $conn_r = $transcript->establishConnection('r');

    // NOTE: Transcripts include a potentially enormous amount of serialized
    // data, so we're loading only some of the fields here if the caller asked
    // for partial records.

    if ($this->needPartialRecords) {
      $fields = implode(
        ', ',
        array(
          'id',
          'phid',
          'objectPHID',
          'time',
          'duration',
          'dryRun',
          'host',
        ));
    } else {
      $fields = '*';
    }

    $rows = queryfx_all(
      $conn_r,
      'SELECT %Q FROM %T t %Q %Q %Q',
      $fields,
      $transcript->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $transcripts = $transcript->loadAllFromArray($rows);

    if ($this->needPartialRecords) {
      // Make sure nothing tries to write these; they aren't complete.
      foreach ($transcripts as $transcript) {
        $transcript->makeEphemeral();
      }
    }

    return $transcripts;
  }

  public function willFilterPage(array $transcripts) {
    $phids = mpull($transcripts, 'getObjectPHID');

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();

    foreach ($transcripts as $key => $transcript) {
      if (empty($objects[$transcript->getObjectPHID()])) {
        $this->didRejectResult($transcript);
        unset($transcripts[$key]);
      }
    }

    return $transcripts;
  }

  public function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


}
