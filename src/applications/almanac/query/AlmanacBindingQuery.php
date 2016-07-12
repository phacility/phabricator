<?php

final class AlmanacBindingQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $servicePHIDs;
  private $devicePHIDs;
  private $interfacePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withServicePHIDs(array $phids) {
    $this->servicePHIDs = $phids;
    return $this;
  }

  public function withDevicePHIDs(array $phids) {
    $this->devicePHIDs = $phids;
    return $this;
  }

  public function withInterfacePHIDs(array $phids) {
    $this->interfacePHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new AlmanacBinding();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $bindings) {
    $service_phids = mpull($bindings, 'getServicePHID');
    $device_phids = mpull($bindings, 'getDevicePHID');
    $interface_phids = mpull($bindings, 'getInterfacePHID');

    $services = id(new AlmanacServiceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($service_phids)
      ->needProperties($this->getNeedProperties())
      ->execute();
    $services = mpull($services, null, 'getPHID');

    $devices = id(new AlmanacDeviceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($device_phids)
      ->needProperties($this->getNeedProperties())
      ->execute();
    $devices = mpull($devices, null, 'getPHID');

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($interface_phids)
      ->needProperties($this->getNeedProperties())
      ->execute();
    $interfaces = mpull($interfaces, null, 'getPHID');

    foreach ($bindings as $key => $binding) {
      $service = idx($services, $binding->getServicePHID());
      $device = idx($devices, $binding->getDevicePHID());
      $interface = idx($interfaces, $binding->getInterfacePHID());
      if (!$service || !$device || !$interface) {
        $this->didRejectResult($binding);
        unset($bindings[$key]);
        continue;
      }

      $binding->attachService($service);
      $binding->attachDevice($device);
      $binding->attachInterface($interface);
    }

    return $bindings;
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

    if ($this->servicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'servicePHID IN (%Ls)',
        $this->servicePHIDs);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->interfacePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'interfacePHID IN (%Ls)',
        $this->interfacePHIDs);
    }

    return $where;
  }

}
