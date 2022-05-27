<?php

final class PhabricatorAuthChallengeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $factorPHIDs;
  private $challengeTTLMin;
  private $challengeTTLMax;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withFactorPHIDs(array $factor_phids) {
    $this->factorPHIDs = $factor_phids;
    return $this;
  }

  public function withChallengeTTLBetween($challenge_min, $challenge_max) {
    $this->challengeTTLMin = $challenge_min;
    $this->challengeTTLMax = $challenge_max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthChallenge();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

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

    if ($this->factorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'factorPHID IN (%Ls)',
        $this->factorPHIDs);
    }

    if ($this->challengeTTLMin !== null) {
      $where[] = qsprintf(
        $conn,
        'challengeTTL >= %d',
        $this->challengeTTLMin);
    }

    if ($this->challengeTTLMax !== null) {
      $where[] = qsprintf(
        $conn,
        'challengeTTL <= %d',
        $this->challengeTTLMax);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
