<?php

final class AlmanacServiceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;
  private $serviceClasses;
  private $needBindings;

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

  public function withServiceClasses(array $classes) {
    $this->serviceClasses = $classes;
    return $this;
  }

  public function needBindings($need_bindings) {
    $this->needBindings = $need_bindings;
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

    if ($this->serviceClasses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'serviceClass IN (%Ls)',
        $this->serviceClasses);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $services) {
    $service_types = AlmanacServiceType::getAllServiceTypes();

    foreach ($services as $key => $service) {
      $service_class = $service->getServiceClass();
      $service_type = idx($service_types, $service_class);
      if (!$service_type) {
        $this->didRejectResult($service);
        unset($services[$key]);
        continue;
      }
      $service->attachServiceType($service_type);
    }

    return $services;
  }

  protected function didFilterPage(array $services) {
    if ($this->needBindings) {
      $service_phids = mpull($services, 'getPHID');
      $bindings = id(new AlmanacBindingQuery())
        ->setViewer($this->getViewer())
        ->withServicePHIDs($service_phids)
        ->execute();
      $bindings = mgroup($bindings, 'getServicePHID');

      foreach ($services as $service) {
        $service_bindings = idx($bindings, $service->getPHID(), array());
        $service->attachBindings($service_bindings);
      }
    }

    return parent::didFilterPage($services);
  }

}
