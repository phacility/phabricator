<?php

final class AlmanacInterfaceQuery
  extends AlmanacQuery {

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

  public function newResultObject() {
    return new AlmanacInterface();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $interfaces) {
    $network_phids = mpull($interfaces, 'getNetworkPHID');
    $device_phids = mpull($interfaces, 'getDevicePHID');

    $networks = id(new AlmanacNetworkQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($network_phids)
      ->needProperties($this->getNeedProperties())
      ->execute();
    $networks = mpull($networks, null, 'getPHID');

    $devices = id(new AlmanacDeviceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($device_phids)
      ->needProperties($this->getNeedProperties())
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

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'interface.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'interface.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->networkPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'interface.networkPHID IN (%Ls)',
        $this->networkPHIDs);
    }

    if ($this->devicePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'interface.devicePHID IN (%Ls)',
        $this->devicePHIDs);
    }

    if ($this->addresses !== null) {
      $parts = array();
      foreach ($this->addresses as $address) {
        $parts[] = qsprintf(
          $conn,
          '(interface.networkPHID = %s '.
            'AND interface.address = %s '.
            'AND interface.port = %d)',
          $address->getNetworkPHID(),
          $address->getAddress(),
          $address->getPort());
      }
      $where[] = implode(' OR ', $parts);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinDeviceTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T device ON device.phid = interface.devicePHID',
        id(new AlmanacDevice())->getTableName());
    }

    return $joins;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinDeviceTable()) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  private function shouldJoinDeviceTable() {
    $vector = $this->getOrderVector();

    if ($vector->containsKey('name')) {
      return true;
    }

    return false;
  }

  protected function getPrimaryTableAlias() {
    return 'interface';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name', 'id'),
        'name' => pht('Device Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => 'device',
        'column' => 'name',
        'type' => 'string',
        'reverse' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $interface = $this->loadCursorObject($cursor);

    $map = array(
      'id' => $interface->getID(),
      'name' => $interface->getDevice()->getName(),
    );

    return $map;
  }

}
