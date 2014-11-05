<?php

final class AlmanacServiceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $names;
  private $needProperties;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function needProperties($need) {
    $this->needProperties = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new AlmanacService();
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

  protected function buildWhereClause($conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn_r,
        'nameIndex IN (%Ls)',
        $hashes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function didFilterPage(array $services) {
    // NOTE: We load properties unconditionally because CustomField assumes
    // it can always generate a list of fields on an object. It may make
    // sense to re-examine that assumption eventually.

    $properties = id(new AlmanacPropertyQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withObjectPHIDs(mpull($services, null, 'getPHID'))
      ->execute();
    $properties = mgroup($properties, 'getObjectPHID');
    foreach ($services as $service) {
      $service_properties = idx($properties, $service->getPHID(), array());
      $service->attachAlmanacProperties($service_properties);
    }

    return $services;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
