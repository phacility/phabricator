<?php

final class PhabricatorAuthPasswordQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $objectPHIDs;
  private $passwordTypes;
  private $isRevoked;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withPasswordTypes(array $types) {
    $this->passwordTypes = $types;
    return $this;
  }

  public function withIsRevoked($is_revoked) {
    $this->isRevoked = $is_revoked;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthPassword();
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

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->passwordTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'passwordType IN (%Ls)',
        $this->passwordTypes);
    }

    if ($this->isRevoked !== null) {
      $where[] = qsprintf(
        $conn,
        'isRevoked = %d',
        (int)$this->isRevoked);
    }

    return $where;
  }

  protected function willFilterPage(array $passwords) {
    $object_phids = mpull($passwords, 'getObjectPHID');

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    foreach ($passwords as $key => $password) {
      $object = idx($objects, $password->getObjectPHID());
      if (!$object) {
        unset($passwords[$key]);
        $this->didRejectResult($password);
        continue;
      }

      $password->attachObject($object);
    }

    return $passwords;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
