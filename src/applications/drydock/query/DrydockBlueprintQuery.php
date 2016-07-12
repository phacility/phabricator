<?php

final class DrydockBlueprintQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $blueprintClasses;
  private $datasourceQuery;
  private $disabled;
  private $authorizedPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBlueprintClasses(array $classes) {
    $this->blueprintClasses = $classes;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function withDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function withAuthorizedPHIDs(array $phids) {
    $this->authorizedPHIDs = $phids;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new DrydockBlueprintNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new DrydockBlueprint();
  }

  protected function getPrimaryTableAlias() {
    return 'blueprint';
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $blueprints) {
    $impls = DrydockBlueprintImplementation::getAllBlueprintImplementations();
    foreach ($blueprints as $key => $blueprint) {
      $impl = idx($impls, $blueprint->getClassName());
      if (!$impl) {
        $this->didRejectResult($blueprint);
        unset($blueprints[$key]);
        continue;
      }
      $impl = clone $impl;
      $blueprint->attachImplementation($impl);
    }

    return $blueprints;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprint.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprint.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprint.blueprintName LIKE %>',
        $this->datasourceQuery);
    }

    if ($this->blueprintClasses !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprint.className IN (%Ls)',
        $this->blueprintClasses);
    }

    if ($this->disabled !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprint.isDisabled = %d',
        (int)$this->disabled);
    }

    return $where;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->authorizedPHIDs !== null) {
      return true;
    }
    return parent::shouldGroupQueryResultRows();
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->authorizedPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T authorization
          ON authorization.blueprintPHID = blueprint.phid
          AND authorization.objectPHID IN (%Ls)
          AND authorization.objectAuthorizationState = %s
          AND authorization.blueprintAuthorizationState = %s',
        id(new DrydockAuthorization())->getTableName(),
        $this->authorizedPHIDs,
        DrydockAuthorization::OBJECTAUTH_ACTIVE,
        DrydockAuthorization::BLUEPRINTAUTH_AUTHORIZED);
    }

    return $joins;
  }

}
