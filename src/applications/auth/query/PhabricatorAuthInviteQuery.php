<?php

final class PhabricatorAuthInviteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $emailAddresses;
  private $verificationCodes;
  private $authorPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withEmailAddresses(array $addresses) {
    $this->emailAddresses = $addresses;
    return $this;
  }

  public function withVerificationCodes(array $codes) {
    $this->verificationCodes = $codes;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorAuthInvite();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $invites = $table->loadAllFromArray($data);

    // If the objects were loaded via verification code, set a flag to make
    // sure the viewer can see them.
    if ($this->verificationCodes !== null) {
      foreach ($invites as $invite) {
        $invite->setViewerHasVerificationCode(true);
      }
    }

    return $invites;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

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

    if ($this->emailAddresses !== null) {
      $where[] = qsprintf(
        $conn,
        'emailAddress IN (%Ls)',
        $this->emailAddresses);
    }

    if ($this->verificationCodes !== null) {
      $hashes = array();
      foreach ($this->verificationCodes as $code) {
        $hashes[] = PhabricatorHash::digestForIndex($code);
      }

      $where[] = qsprintf(
        $conn,
        'verificationHash IN (%Ls)',
        $hashes);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    // NOTE: This query is issued by logged-out users, who often will not be
    // able to see applications. They still need to be able to see invites.
    return null;
  }

}
