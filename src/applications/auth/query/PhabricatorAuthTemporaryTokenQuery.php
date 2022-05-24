<?php

final class PhabricatorAuthTemporaryTokenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $tokenResources;
  private $tokenTypes;
  private $userPHIDs;
  private $expired;
  private $tokenCodes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withTokenResources(array $resources) {
    $this->tokenResources = $resources;
    return $this;
  }

  public function withTokenTypes(array $types) {
    $this->tokenTypes = $types;
    return $this;
  }

  public function withExpired($expired) {
    $this->expired = $expired;
    return $this;
  }

  public function withTokenCodes(array $codes) {
    $this->tokenCodes = $codes;
    return $this;
  }

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthTemporaryToken();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->tokenResources !== null) {
      $where[] = qsprintf(
        $conn,
        'tokenResource IN (%Ls)',
        $this->tokenResources);
    }

    if ($this->tokenTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'tokenType IN (%Ls)',
        $this->tokenTypes);
    }

    if ($this->expired !== null) {
      if ($this->expired) {
        $where[] = qsprintf(
          $conn,
          'tokenExpires <= %d',
          time());
      } else {
        $where[] = qsprintf(
          $conn,
          'tokenExpires > %d',
          time());
      }
    }

    if ($this->tokenCodes !== null) {
      $where[] = qsprintf(
        $conn,
        'tokenCode IN (%Ls)',
        $this->tokenCodes);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
