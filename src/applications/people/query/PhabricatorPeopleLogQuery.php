<?php

final class PhabricatorPeopleLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $actorPHIDs;
  private $userPHIDs;
  private $relatedPHIDs;
  private $sessionKeys;
  private $actions;
  private $remoteAddressPrefix;
  private $dateCreatedMin;
  private $dateCreatedMax;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

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

  public function withDateCreatedBetween($min, $max) {
    $this->dateCreatedMin = $min;
    $this->dateCreatedMax = $max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorUserLog();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->actorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'actorPHID IN (%Ls)',
        $this->actorPHIDs);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->relatedPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '(actorPHID IN (%Ls) OR userPHID IN (%Ls))',
        $this->relatedPHIDs,
        $this->relatedPHIDs);
    }

    if ($this->sessionKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'session IN (%Ls)',
        $this->sessionKeys);
    }

    if ($this->actions !== null) {
      $where[] = qsprintf(
        $conn,
        'action IN (%Ls)',
        $this->actions);
    }

    if ($this->remoteAddressPrefix !== null) {
      $where[] = qsprintf(
        $conn,
        'remoteAddr LIKE %>',
        $this->remoteAddressPrefix);
    }

    if ($this->dateCreatedMin !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated >= %d',
        $this->dateCreatedMin);
    }

    if ($this->dateCreatedMax !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated <= %d',
        $this->dateCreatedMax);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

}
