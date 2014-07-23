<?php

final class PhabricatorAuthSessionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $identityPHIDs;
  private $sessionKeys;
  private $sessionTypes;

  public function withIdentityPHIDs(array $identity_phids) {
    $this->identityPHIDs = $identity_phids;
    return $this;
  }

  public function withSessionKeys(array $keys) {
    $this->sessionKeys = $keys;
    return $this;
  }

  public function withSessionTypes(array $types) {
    $this->sessionTypes = $types;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorAuthSession();
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

  protected function willFilterPage(array $sessions) {
    $identity_phids = mpull($sessions, 'getUserPHID');

    $identity_objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($identity_phids)
      ->execute();
    $identity_objects = mpull($identity_objects, null, 'getPHID');

    foreach ($sessions as $key => $session) {
      $identity_object = idx($identity_objects, $session->getUserPHID());
      if (!$identity_object) {
        unset($sessions[$key]);
      } else {
        $session->attachIdentityObject($identity_object);
      }
    }

    return $sessions;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->identityPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->identityPHIDs);
    }

    if ($this->sessionKeys) {
      $hashes = array();
      foreach ($this->sessionKeys as $session_key) {
        $hashes[] = PhabricatorHash::digest($session_key);
      }
      $where[] = qsprintf(
        $conn_r,
        'sessionKey IN (%Ls)',
        $hashes);
    }

    if ($this->sessionTypes) {
      $where[] = qsprintf(
        $conn_r,
        'type IN (%Ls)',
        $this->sessionTypes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
