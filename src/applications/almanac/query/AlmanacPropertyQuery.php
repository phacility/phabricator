<?php

final class AlmanacPropertyQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $objects;
  private $names;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function withObjects(array $objects) {
    $this->objects = mpull($objects, null, 'getPHID');
    $this->objectPHIDs = array_keys($this->objects);
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function newResultObject() {
    return new AlmanacProperty();
  }

  protected function willFilterPage(array $properties) {
    $object_phids = mpull($properties, 'getObjectPHID');

    $object_phids = array_fuse($object_phids);

    if ($this->objects !== null) {
      $object_phids = array_diff_key($object_phids, $this->objects);
    }

    if ($object_phids) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($object_phids)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
    } else {
      $objects = array();
    }

    $objects += $this->objects;

    foreach ($properties as $key => $property) {
      $object = idx($objects, $property->getObjectPHID());
      if (!$object) {
        unset($properties[$key]);
        continue;
      }
      $property->attachObject($object);
    }

    return $properties;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }
      $where[] = qsprintf(
        $conn,
        'fieldIndex IN (%Ls)',
        $hashes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
