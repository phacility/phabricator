<?php

final class AlmanacServiceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;
  private $serviceTypes;
  private $devicePHIDs;
  private $namePrefix;
  private $nameSuffix;

  private $needBindings;
  private $needActiveBindings;

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

  public function withServiceTypes(array $types) {
    $this->serviceTypes = $types;
    return $this;
  }

  public function withDevicePHIDs(array $phids) {
    $this->devicePHIDs = $phids;
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

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new AlmanacServiceNameNgrams(),
      $ngrams);
  }

  public function needBindings($need_bindings) {
    $this->needBindings = $need_bindings;
    return $this;
  }

  public function needActiveBindings($need_active) {
    $this->needActiveBindings = $need_active;
    return $this;
  }

  public function newResultObject() {
    return new AlmanacService();
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinBindingTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T binding ON service.phid = binding.servicePHID',
        id(new AlmanacBinding())->getTableName());
    }

    return $joins;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'service.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'service.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn,
        'service.nameIndex IN (%Ls)',
        $hashes);
    }

    if ($this->serviceTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'service.serviceType IN (%Ls)',
        $this->serviceTypes);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'binding.devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->namePrefix !== null) {
      $where[] = qsprintf(
        $conn,
        'service.name LIKE %>',
        $this->namePrefix);
    }

    if ($this->nameSuffix !== null) {
      $where[] = qsprintf(
        $conn,
        'service.name LIKE %<',
        $this->nameSuffix);
    }

    return $where;
  }

  protected function willFilterPage(array $services) {
    $service_map = AlmanacServiceType::getAllServiceTypes();

    foreach ($services as $key => $service) {
      $implementation = idx($service_map, $service->getServiceType());

      if (!$implementation) {
        $this->didRejectResult($service);
        unset($services[$key]);
        continue;
      }

      $implementation = clone $implementation;
      $service->attachServiceImplementation($implementation);
    }

    return $services;
  }

  protected function didFilterPage(array $services) {
    $need_all = $this->needBindings;
    $need_active = $this->needActiveBindings;

    $need_any = ($need_all || $need_active);
    $only_active = ($need_active && !$need_all);

    if ($need_any) {
      $service_phids = mpull($services, 'getPHID');

      $bindings_query = id(new AlmanacBindingQuery())
        ->setViewer($this->getViewer())
        ->withServicePHIDs($service_phids)
        ->needProperties($this->getNeedProperties());

      if ($only_active) {
        $bindings_query->withIsActive(true);
      }

      $bindings = $bindings_query->execute();
      $bindings = mgroup($bindings, 'getServicePHID');

      foreach ($services as $service) {
        $service_bindings = idx($bindings, $service->getPHID(), array());

        if ($only_active) {
          $service->attachActiveBindings($service_bindings);
        } else {
          $service->attachBindings($service_bindings);
        }
      }
    }

    return parent::didFilterPage($services);
  }

  private function shouldJoinBindingTable() {
    return ($this->devicePHIDs !== null);
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinBindingTable()) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  protected function getPrimaryTableAlias() {
    return 'service';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'name',
        'type' => 'string',
        'unique' => true,
        'reverse' => true,
      ),
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'name' => $object->getName(),
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
