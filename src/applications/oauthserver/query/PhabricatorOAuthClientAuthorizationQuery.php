<?php

final class PhabricatorOAuthClientAuthorizationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $userPHIDs;

  public function witHPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }

  public function loadPage() {
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

  public function willFilterPage(array $authorizations) {
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
      }
      $authorization->attachClient($client);
    }

    return $authorizations;
  }

  private function buildWhereClause($conn_r) {
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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationOAuthServer';
  }

}
