<?php

final class PhabricatorPeopleLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $actorPHIDs;
  private $userPHIDs;
  private $relatedPHIDs;
  private $sessionKeys;
  private $actions;
  private $remoteAddressPrefix;

  public function withActorPHIDs(array $actor_phids) {
    $this->actorPHIDs = $actor_phids;
    return $this;
  }

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withRelatedPHIDs(array $related_phids) {
    $this->relatedPHIDs = $related_phids;
    return $this;
  }

  public function withSessionKeys(array $session_keys) {
    $this->sessionKeys = $session_keys;
    return $this;
  }

  public function withActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function withRemoteAddressPrefix($remote_address_prefix) {
    $this->remoteAddressPrefix = $remote_address_prefix;
    return $this;
  }

  protected function loadPage() {
    $table  = new PhabricatorUserLog();
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

    if ($this->actorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'actorPHID IN (%Ls)',
        $this->actorPHIDs);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->relatedPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'actorPHID IN (%Ls) OR userPHID IN (%Ls)',
        $this->relatedPHIDs,
        $this->relatedPHIDs);
    }

    if ($this->sessionKeys !== null) {
      $where[] = qsprintf(
        $conn_r,
        'session IN (%Ls)',
        $this->sessionKeys);
    }

    if ($this->actions !== null) {
      $where[] = qsprintf(
        $conn_r,
        'action IN (%Ls)',
        $this->actions);
    }

    if ($this->remoteAddressPrefix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'remoteAddr LIKE %>',
        $this->remoteAddressPrefix);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

}
