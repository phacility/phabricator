<?php

final class HeraldTranscriptQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $objectPHIDs;
  private $needPartialRecords;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function needPartialRecords($need_partial) {
    $this->needPartialRecords = $need_partial;
    return $this;
  }

  protected function loadPage() {
    $transcript = new HeraldTranscript();
    $conn = $transcript->establishConnection('r');

    // NOTE: Transcripts include a potentially enormous amount of serialized
    // data, so we're loading only some of the fields here if the caller asked
    // for partial records.

    if ($this->needPartialRecords) {
      $fields = array(
        'id',
        'phid',
        'objectPHID',
        'time',
        'duration',
        'dryRun',
        'host',
      );
      $fields = qsprintf($conn, '%LC', $fields);
    } else {
      $fields = qsprintf($conn, '*');
    }

    $rows = queryfx_all(
      $conn,
      'SELECT %Q FROM %T t %Q %Q %Q',
      $fields,
      $transcript->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    $transcripts = $transcript->loadAllFromArray($rows);

    if ($this->needPartialRecords) {
      // Make sure nothing tries to write these; they aren't complete.
      foreach ($transcripts as $transcript) {
        $transcript->makeEphemeral();
      }
    }

    return $transcripts;
  }

  protected function willFilterPage(array $transcripts) {
    $phids = mpull($transcripts, 'getObjectPHID');

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();

    foreach ($transcripts as $key => $transcript) {
      $object_phid = $transcript->getObjectPHID();

      if (!$object_phid) {
        $transcript->attachObject(null);
        continue;
      }

      $object = idx($objects, $object_phid);
      if (!$object) {
        $this->didRejectResult($transcript);
        unset($transcripts[$key]);
      }

      $transcript->attachObject($object);
    }

    return $transcripts;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn,
        'objectPHID in (%Ls)',
        $this->objectPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

}
