<?php

final class LegalpadDocumentSignatureQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $documentPHIDs;
  private $signerPHIDs;
  private $documentVersions;
  private $secretKeys;
  private $nameContains;
  private $emailContains;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withDocumentPHIDs(array $phids) {
    $this->documentPHIDs = $phids;
    return $this;
  }

  public function withSignerPHIDs(array $phids) {
    $this->signerPHIDs = $phids;
    return $this;
  }

  public function withDocumentVersions(array $versions) {
    $this->documentVersions = $versions;
    return $this;
  }

  public function withSecretKeys(array $keys) {
    $this->secretKeys = $keys;
    return $this;
  }

  public function withNameContains($text) {
    $this->nameContains = $text;
    return $this;
  }

  public function withEmailContains($text) {
    $this->emailContains = $text;
    return $this;
  }

  protected function loadPage() {
    $table = new LegalpadDocumentSignature();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $signatures = $table->loadAllFromArray($data);

    return $signatures;
  }

  protected function willFilterPage(array $signatures) {
    $document_phids = mpull($signatures, 'getDocumentPHID');

    $documents = id(new LegalpadDocumentQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($document_phids)
      ->execute();
    $documents = mpull($documents, null, 'getPHID');

    foreach ($signatures as $key => $signature) {
      $document_phid = $signature->getDocumentPHID();
      $document = idx($documents, $document_phid);
      if ($document) {
        $signature->attachDocument($document);
      } else {
        unset($signatures[$key]);
      }
    }

    return $signatures;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->documentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'documentPHID IN (%Ls)',
        $this->documentPHIDs);
    }

    if ($this->signerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'signerPHID IN (%Ls)',
        $this->signerPHIDs);
    }

    if ($this->documentVersions !== null) {
      $where[] = qsprintf(
        $conn,
        'documentVersion IN (%Ld)',
        $this->documentVersions);
    }

    if ($this->secretKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'secretKey IN (%Ls)',
        $this->secretKeys);
    }

    if ($this->nameContains !== null) {
      $where[] = qsprintf(
        $conn,
        'signerName LIKE %~',
        $this->nameContains);
    }

    if ($this->emailContains !== null) {
      $where[] = qsprintf(
        $conn,
        'signerEmail LIKE %~',
        $this->emailContains);
    }

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorLegalpadApplication';
  }

}
