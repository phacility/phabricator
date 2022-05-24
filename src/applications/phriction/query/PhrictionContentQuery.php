<?php

final class PhrictionContentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $documentPHIDs;
  private $versions;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDocumentPHIDs(array $phids) {
    $this->documentPHIDs = $phids;
    return $this;
  }

  public function withVersions(array $versions) {
    $this->versions = $versions;
    return $this;
  }

  public function newResultObject() {
    return new PhrictionContent();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'c.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'c.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->versions !== null) {
      $where[] = qsprintf(
        $conn,
        'version IN (%Ld)',
        $this->versions);
    }

    if ($this->documentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'd.phid IN (%Ls)',
        $this->documentPHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinDocumentTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T d ON d.phid = c.documentPHID',
        id(new PhrictionDocument())->getTableName());
    }

    return $joins;
  }

  protected function willFilterPage(array $contents) {
    $document_phids = mpull($contents, 'getDocumentPHID');

    $documents = id(new PhrictionDocumentQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($document_phids)
      ->execute();
    $documents = mpull($documents, null, 'getPHID');

    foreach ($contents as $key => $content) {
      $document_phid = $content->getDocumentPHID();

      $document = idx($documents, $document_phid);
      if (!$document) {
        unset($contents[$key]);
        $this->didRejectResult($content);
        continue;
      }

      $content->attachDocument($document);
    }

    return $contents;
  }

  private function shouldJoinDocumentTable() {
    if ($this->documentPHIDs !== null) {
      return true;
    }

    return false;
  }

  protected function getPrimaryTableAlias() {
    return 'c';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

}
