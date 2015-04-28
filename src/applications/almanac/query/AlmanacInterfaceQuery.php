<?php

final class AlmanacInterfaceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $networkPHIDs;
  private $devicePHIDs;
  private $addresses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNetworkPHIDs(array $phids) {
    $this->networkPHIDs = $phids;
    return $this;
  }

  public function withDevicePHIDs(array $phids) {
    $this->devicePHIDs = $phids;
    return $this;
  }

  public function withAddresses(array $addresses) {
    $this->addresses = $addresses;
    return $this;
  }

  protected function loadPage() {
    $table = new AlmanacInterface();
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

  protected function willFilterPage(array $interfaces) {
    $network_phids = mpull($interfaces, 'getNetworkPHID');
    $device_phids = mpull($interfaces, 'getDevicePHID');

    $networks = id(new AlmanacNetworkQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($network_phids)
      ->execute();
    $networks = mpull($networks, null, 'getPHID');

    $devices = id(new AlmanacDeviceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($device_phids)
      ->execute();
    $devices = mpull($devices, null, 'getPHID');

    foreach ($interfaces as $key => $interface) {
      $network = idx($networks, $interface->getNetworkPHID());
      $device = idx($devices, $interface->getDevicePHID());
      if (!$network || !$device) {
        $this->didRejectResult($interface);
        unset($interfaces[$key]);
        continue;
      }

      $interface->attachNetwork($network);
      $interface->attachDevice($device);
    }

    return $interfaces;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->networkPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'networkPHID IN (%Ls)',
        $this->networkPHIDs);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->addresses !== null) {
      $parts = array();
      foreach ($this->addresses as $address) {
        $parts[] = qsprintf(
          $conn_r,
          '(networkPHID = %s AND address = %s AND port = %d)',
          $address->getNetworkPHID(),
          $address->getAddress(),
          $address->getPort());
      }
      $where[] = implode(' OR ', $parts);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
