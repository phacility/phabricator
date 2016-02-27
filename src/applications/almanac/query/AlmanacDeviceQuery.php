<?php

final class AlmanacDeviceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;
  private $namePrefix;
  private $nameSuffix;

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
      new AlmanacDeviceNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new AlmanacDevice();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'device.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'device.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }
      $where[] = qsprintf(
        $conn,
        'device.nameIndex IN (%Ls)',
        $hashes);
    }

    if ($this->namePrefix !== null) {
      $where[] = qsprintf(
        $conn,
        'device.name LIKE %>',
        $this->namePrefix);
    }

    if ($this->nameSuffix !== null) {
      $where[] = qsprintf(
        $conn,
        'device.name LIKE %<',
        $this->nameSuffix);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'device';
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

  protected function getPagingValueMap($cursor, array $keys) {
    $device = $this->loadCursorObject($cursor);
    return array(
      'id' => $device->getID(),
      'name' => $device->getName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Device Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
