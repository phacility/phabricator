<?php

final class PhabricatorAuthSSHKeyQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const AUTHSTRUCT_CACHEKEY = 'ssh.authstruct';

  private $ids;
  private $phids;
  private $objectPHIDs;
  private $keys;
  private $isActive;

  public static function deleteSSHKeyCache() {
    $cache = PhabricatorCaches::getMutableCache();
    $authfile_key = self::AUTHSTRUCT_CACHEKEY;
    $cache->deleteKey($authfile_key);
  }

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

  public function withKeys(array $keys) {
    assert_instances_of($keys, 'PhabricatorAuthSSHPublicKey');
    $this->keys = $keys;
    return $this;
  }

  public function withIsActive($active) {
    $this->isActive = $active;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthSSHKey();
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
        $this->didRejectResult($ssh_key);
        unset($keys[$key]);
        continue;
      }

      $ssh_key->attachObject($object);
    }

    return $keys;
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

    if ($this->keys !== null) {
      $sql = array();
      foreach ($this->keys as $key) {
        $sql[] = qsprintf(
          $conn,
          '(keyType = %s AND keyIndex = %s)',
          $key->getType(),
          $key->getHash());
      }
      $where[] = qsprintf($conn, '%LO', $sql);
    }

    if ($this->isActive !== null) {
      if ($this->isActive) {
        $where[] = qsprintf(
          $conn,
          'isActive = %d',
          1);
      } else {
        $where[] = qsprintf(
          $conn,
          'isActive IS NULL');
      }
    }

    return $where;

  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
