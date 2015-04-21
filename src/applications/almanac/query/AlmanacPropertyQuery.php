<?php

final class AlmanacPropertyQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $names;
  private $disablePolicyFilteringAndAttachment;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function setDisablePolicyFilteringAndAttachment($disable) {
    $this->disablePolicyFilteringAndAttachment = $disable;
    return $this;
  }

  protected function shouldDisablePolicyFiltering() {
    return $this->disablePolicyFilteringAndAttachment;
  }

  protected function loadPage() {
    $table = new AlmanacProperty();
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

  protected function willFilterPage(array $properties) {
    if (!$this->disablePolicyFilteringAndAttachment) {
      $object_phids = mpull($properties, 'getObjectPHID');

      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($object_phids)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');

      foreach ($properties as $key => $property) {
        $object = idx($objects, $property->getObjectPHID());
        if (!$object) {
          unset($properties[$key]);
          continue;
        }
        $property->attachObject($object);
      }
    }

    return $properties;
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

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }
      $where[] = qsprintf(
        $conn_r,
        'fieldIndex IN (%Ls)',
        $hashes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
