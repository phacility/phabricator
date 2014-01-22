<?php

final class LegalpadDocumentSignatureQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $documentPHIDs;
  private $signerPHIDs;
  private $documentVersions;
  private $secretKeys;

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

    $documents = $table->loadAllFromArray($data);

    return $documents;
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

    if ($this->documentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'documentPHID IN (%Ls)',
        $this->documentPHIDs);
    }

    if ($this->signerPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'signerPHID IN (%Ls)',
        $this->signerPHIDs);
    }

    if ($this->documentVersions) {
      $where[] = qsprintf(
        $conn_r,
        'documentVersion IN (%Ld)',
        $this->documentVersions);
    }

    if ($this->secretKeys) {
      $where[] = qsprintf(
        $conn_r,
        'secretKey IN (%Ls)',
        $this->secretKeys);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationLegalpad';
  }

}
