<?php

final class PhabricatorConduitTokenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $expired;
  private $tokens;
  private $tokenTypes;

  public function withExpired($expired) {
    $this->expired = $expired;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function withTokens(array $tokens) {
    $this->tokens = $tokens;
    return $this;
  }

  public function withTokenTypes(array $types) {
    $this->tokenTypes = $types;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorConduitToken();
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->tokens !== null) {
      $where[] = qsprintf(
        $conn_r,
        'token IN (%Ls)',
        $this->tokens);
    }

    if ($this->tokenTypes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'tokenType IN (%Ls)',
        $this->tokenTypes);
    }

    if ($this->expired !== null) {
      if ($this->expired) {
        $where[] = qsprintf(
          $conn_r,
          'expires <= %d',
          PhabricatorTime::getNow());
      } else {
        $where[] = qsprintf(
          $conn_r,
          'expires IS NULL OR expires > %d',
          PhabricatorTime::getNow());
      }
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $tokens) {
    $object_phids = mpull($tokens, 'getObjectPHID');
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    foreach ($tokens as $key => $token) {
      $object = idx($objects, $token->getObjectPHID(), null);
      if (!$object) {
        $this->didRejectResult($token);
        unset($tokens[$key]);
        continue;
      }
      $token->attachObject($object);
    }

    return $tokens;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
