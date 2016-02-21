<?php

final class AlmanacNamespaceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;

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

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new AlmanacNamespaceNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new AlmanacNamespace();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'namespace.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'namespace.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'namespace.name IN (%Ls)',
        $this->names);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'namespace';
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
    $namespace = $this->loadCursorObject($cursor);
    return array(
      'id' => $namespace->getID(),
      'name' => $namespace->getName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Namespace Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
