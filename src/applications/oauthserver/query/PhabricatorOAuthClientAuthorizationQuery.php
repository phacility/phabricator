<?php

final class PhabricatorOAuthClientAuthorizationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $userPHIDs;
  private $clientPHIDs;

  public function withPHIDs(array $phids) {
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

  public function newResultObject() {
    return new PhabricatorOAuthClientAuthorization();
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
        $this->didRejectResult($authorization);
        unset($authorizations[$key]);
        continue;
      }

      $authorization->attachClient($client);
    }

    return $authorizations;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->clientPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'clientPHID IN (%Ls)',
        $this->clientPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorOAuthServerApplication';
  }

}
