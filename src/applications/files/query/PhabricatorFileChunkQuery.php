<?php

final class PhabricatorFileChunkQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $chunkHandles;
  private $rangeStart;
  private $rangeEnd;
  private $isComplete;
  private $needDataFiles;

  public function withChunkHandles(array $handles) {
    $this->chunkHandles = $handles;
    return $this;
  }

  public function withByteRange($start, $end) {
    $this->rangeStart = $start;
    $this->rangeEnd = $end;
    return $this;
  }

  public function withIsComplete($complete) {
    $this->isComplete = $complete;
    return $this;
  }

  public function needDataFiles($need) {
    $this->needDataFiles = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorFileChunk();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $chunks) {

    if ($this->needDataFiles) {
      $file_phids = mpull($chunks, 'getDataFilePHID');
      $file_phids = array_filter($file_phids);
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setViewer($this->getViewer())
          ->setParentQuery($this)
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($chunks as $key => $chunk) {
        $data_phid = $chunk->getDataFilePHID();
        if (!$data_phid) {
          $chunk->attachDataFile(null);
          continue;
        }

        $file = idx($files, $data_phid);
        if (!$file) {
          unset($chunks[$key]);
          $this->didRejectResult($chunk);
          continue;
        }

        $chunk->attachDataFile($file);
      }

      if (!$chunks) {
        return $chunks;
      }
    }

    return $chunks;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->chunkHandles !== null) {
      $where[] = qsprintf(
        $conn_r,
        'chunkHandle IN (%Ls)',
        $this->chunkHandles);
    }

    if ($this->rangeStart !== null) {
      $where[] = qsprintf(
        $conn_r,
        'byteEnd > %d',
        $this->rangeStart);
    }

    if ($this->rangeEnd !== null) {
      $where[] = qsprintf(
        $conn_r,
        'byteStart < %d',
        $this->rangeEnd);
    }

    if ($this->isComplete !== null) {
      if ($this->isComplete) {
        $where[] = qsprintf(
          $conn_r,
          'dataFilePHID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn_r,
          'dataFilePHID IS NULL');
      }
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFilesApplication';
  }

}
