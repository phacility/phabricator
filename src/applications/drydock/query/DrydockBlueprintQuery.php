<?php

final class DrydockBlueprintQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $blueprintClasses;
  private $datasourceQuery;
  private $disabled;
  private $authorizedPHIDs;

  private $identifiers;
  private $identifierIDs;
  private $identifierPHIDs;
  private $identifierMap;

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

  public function withIdentifiers(array $identifiers) {
    if (!$identifiers) {
      throw new Exception(
        pht(
          'Can not issue a query with an empty identifier list.'));
    }

    $this->identifiers = $identifiers;

    $ids = array();
    $phids = array();

    foreach ($identifiers as $identifier) {
      if (ctype_digit($identifier)) {
        $ids[] = $identifier;
      } else {
        $phids[] = $identifier;
      }
    }

    $this->identifierIDs = $ids;
    $this->identifierPHIDs = $phids;

    return $this;
  }

  public function getIdentifierMap() {
    if ($this->identifierMap === null) {
      throw new Exception(
        pht(
          'Execute a query with identifiers before getting the '.
          'identifier map.'));
    }

    return $this->identifierMap;
  }

  public function newResultObject() {
    return new DrydockBlueprint();
  }

  protected function getPrimaryTableAlias() {
    return 'blueprint';
  }

  protected function willExecute() {
    if ($this->identifiers) {
      $this->identifierMap = array();
    } else {
      $this->identifierMap = null;
    }
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

    if ($this->identifiers) {
      $id_map = mpull($blueprints, null, 'getID');
      $phid_map = mpull($blueprints, null, 'getPHID');

      $map = $this->identifierMap;

      foreach ($this->identifierIDs as $id) {
        if (isset($id_map[$id])) {
          $map[$id] = $id_map[$id];
        }
      }

      foreach ($this->identifierPHIDs as $phid) {
        if (isset($phid_map[$phid])) {
          $map[$phid] = $phid_map[$phid];
        }
      }

      // Just for consistency, reorder the map to match input order.
      $map = array_select_keys($map, $this->identifiers);

      $this->identifierMap = $map;
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

    if ($this->identifiers !== null) {
      $parts = array();

      if ($this->identifierIDs) {
        $parts[] = qsprintf(
          $conn,
          'blueprint.id IN (%Ld)',
          $this->identifierIDs);
      }

      if ($this->identifierPHIDs) {
        $parts[] = qsprintf(
          $conn,
          'blueprint.phid IN (%Ls)',
          $this->identifierPHIDs);
      }

      $where[] = qsprintf(
        $conn,
        '%LO',
        $parts);
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
