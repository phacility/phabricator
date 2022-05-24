<?php

final class PhabricatorAuthSessionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
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

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthSession();
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

    if ($this->identityPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->identityPHIDs);
    }

    if ($this->sessionKeys !== null) {
      $hashes = array();
      foreach ($this->sessionKeys as $session_key) {
        $hashes[] = PhabricatorAuthSession::newSessionDigest(
          new PhutilOpaqueEnvelope($session_key));
      }
      $where[] = qsprintf(
        $conn,
        'sessionKey IN (%Ls)',
        $hashes);
    }

    if ($this->sessionTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'type IN (%Ls)',
        $this->sessionTypes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
