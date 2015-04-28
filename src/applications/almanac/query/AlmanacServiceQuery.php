<?php

final class AlmanacServiceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;
  private $serviceClasses;
  private $devicePHIDs;
  private $locked;
  private $namePrefix;
  private $nameSuffix;

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

  public function withDevicePHIDs(array $phids) {
    $this->devicePHIDs = $phids;
    return $this;
  }

  public function withLocked($locked) {
    $this->locked = $locked;
    return $this;
  }

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function withNameSuffix($suffix) {
    $this->nameSuffix = $suffix;
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
      'SELECT service.* FROM %T service %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->devicePHIDs !== null) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T binding ON service.phid = binding.servicePHID',
        id(new AlmanacBinding())->getTableName());
    }

    return implode(' ', $joins);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn_r,
        'service.nameIndex IN (%Ls)',
        $hashes);
    }

    if ($this->serviceClasses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.serviceClass IN (%Ls)',
        $this->serviceClasses);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'binding.devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->locked !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.isLocked = %d',
        (int)$this->locked);
    }

    if ($this->namePrefix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.name LIKE %>',
        $this->namePrefix);
    }

    if ($this->nameSuffix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'service.name LIKE %<',
        $this->nameSuffix);
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

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => 'service',
        'column' => 'name',
        'type' => 'string',
        'unique' => true,
        'reverse' => true,
      ),
    );
  }

  protected function getValueMap($cursor, array $keys) {
    $service = $this->loadCursorObject($cursor);
    return array(
      'id' => $service->getID(),
      'name' => $service->getServiceName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Service Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

}
