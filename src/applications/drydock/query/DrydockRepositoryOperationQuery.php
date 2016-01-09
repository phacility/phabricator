<?php

final class DrydockRepositoryOperationQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $objectPHIDs;
  private $repositoryPHIDs;
  private $operationStates;
  private $operationTypes;
  private $isDismissed;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  public function withOperationStates(array $states) {
    $this->operationStates = $states;
    return $this;
  }

  public function withOperationTypes(array $types) {
    $this->operationTypes = $types;
    return $this;
  }

  public function withIsDismissed($dismissed) {
    $this->isDismissed = $dismissed;
    return $this;
  }

  public function newResultObject() {
    return new DrydockRepositoryOperation();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $operations) {
    $implementations = DrydockRepositoryOperationType::getAllOperationTypes();

    foreach ($operations as $key => $operation) {
      $impl = idx($implementations, $operation->getOperationType());
      if (!$impl) {
        $this->didRejectResult($operation);
        unset($operations[$key]);
        continue;
      }
      $impl = clone $impl;
      $operation->attachImplementation($impl);
    }

    $repository_phids = mpull($operations, 'getRepositoryPHID');
    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($repository_phids)
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    } else {
      $repositories = array();
    }

    foreach ($operations as $key => $operation) {
      $repository = idx($repositories, $operation->getRepositoryPHID());
      if (!$repository) {
        $this->didRejectResult($operation);
        unset($operations[$key]);
        continue;
      }
      $operation->attachRepository($repository);
    }

    return $operations;
  }

  protected function didFilterPage(array $operations) {
    $object_phids = mpull($operations, 'getObjectPHID');
    if ($object_phids) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($object_phids)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
    } else {
      $objects = array();
    }

    foreach ($operations as $key => $operation) {
      $object = idx($objects, $operation->getObjectPHID());
      $operation->attachObject($object);
    }

    return $operations;
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

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->operationStates !== null) {
      $where[] = qsprintf(
        $conn,
        'operationState IN (%Ls)',
        $this->operationStates);
    }

    if ($this->operationTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'operationType IN (%Ls)',
        $this->operationTypes);
    }

    if ($this->isDismissed !== null) {
      $where[] = qsprintf(
        $conn,
        'isDismissed = %d',
        (int)$this->isDismissed);
    }

    return $where;
  }

}
