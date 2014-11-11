<?php

final class PhabricatorAuthSSHKeyQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $keys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withKeys(array $keys) {
    assert_instances_of($keys, 'PhabricatorAuthSSHPublicKey');
    $this->keys = $keys;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorAuthSSHKey();
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

  protected function willFilterPage(array $keys) {
    $object_phids = mpull($keys, 'getObjectPHID');

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    foreach ($keys as $key => $ssh_key) {
      $object = idx($objects, $ssh_key->getObjectPHID());

      // We must have an object, and that object must be a valid object for
      // SSH keys.
      if (!$object || !($object instanceof PhabricatorSSHPublicKeyInterface)) {
        unset($keys[$key]);
        continue;
      }

      $ssh_key->attachObject($object);
    }

    return $keys;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->keys !== null) {
      $sql = array();
      foreach ($this->keys as $key) {
        $sql[] = qsprintf(
          $conn_r,
          '(keyType = %s AND keyIndex = %s)',
          $key->getType(),
          $key->getHash());
      }
      $where[] = implode(' OR ', $sql);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
