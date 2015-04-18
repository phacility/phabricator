<?php

final class PhabricatorOAuthClientAuthorizationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $userPHIDs;
  private $clientPHIDs;

  public function witHPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }

  public function withClientPHIDs(array $phids) {
    $this->clientPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table  = new PhabricatorOAuthClientAuthorization();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T auth %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $authorizations) {
    $client_phids = mpull($authorizations, 'getClientPHID');

    $clients = id(new PhabricatorOAuthServerClientQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($client_phids)
      ->execute();
    $clients = mpull($clients, null, 'getPHID');

    foreach ($authorizations as $key => $authorization) {
      $client = idx($clients, $authorization->getClientPHID());
      if (!$client) {
        unset($authorizations[$key]);
        continue;
      }
      $authorization->attachClient($client);
    }

    return $authorizations;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->userPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->clientPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'clientPHID IN (%Ls)',
        $this->clientPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorOAuthServerApplication';
  }

}
