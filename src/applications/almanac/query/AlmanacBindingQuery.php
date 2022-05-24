<?php

final class AlmanacBindingQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $servicePHIDs;
  private $devicePHIDs;
  private $interfacePHIDs;
  private $isActive;

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

  public function withIsActive($active) {
    $this->isActive = $active;
    return $this;
  }

  public function newResultObject() {
    return new AlmanacBinding();
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
        'binding.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'binding.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->servicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'binding.servicePHID IN (%Ls)',
        $this->servicePHIDs);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'binding.devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->interfacePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'binding.interfacePHID IN (%Ls)',
        $this->interfacePHIDs);
    }

    if ($this->isActive !== null) {
      if ($this->isActive) {
        $where[] = qsprintf(
          $conn,
          '(binding.isDisabled = 0) AND (device.status IN (%Ls))',
          AlmanacDeviceStatus::getActiveStatusList());
      } else {
        $where[] = qsprintf(
          $conn,
          '(binding.isDisabled = 1) OR (device.status IN (%Ls))',
          AlmanacDeviceStatus::getDisabledStatusList());
      }
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinDeviceTable()) {
      $device_table = new AlmanacDevice();
      $joins[] = qsprintf(
        $conn,
        'JOIN %R device ON binding.devicePHID = device.phid',
        $device_table);
    }

    return $joins;
  }

  private function shouldJoinDeviceTable() {
    if ($this->isActive !== null) {
      return true;
    }

    return false;
  }

  protected function getPrimaryTableAlias() {
    return 'binding';
  }

}
