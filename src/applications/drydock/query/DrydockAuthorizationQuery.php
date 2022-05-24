<?php

final class DrydockAuthorizationQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $blueprintPHIDs;
  private $objectPHIDs;
  private $blueprintStates;
  private $objectStates;

  public static function isFullyAuthorized(
    $object_phid,
    array $blueprint_phids) {

    if (!$blueprint_phids) {
      return true;
    }

    $authorizations = id(new self())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withObjectPHIDs(array($object_phid))
      ->withBlueprintPHIDs($blueprint_phids)
      ->execute();
    $authorizations = mpull($authorizations, null, 'getBlueprintPHID');

    foreach ($blueprint_phids as $phid) {
      $authorization = idx($authorizations, $phid);
      if (!$authorization) {
        return false;
      }

      if (!$authorization->isAuthorized()) {
        return false;
      }
    }

    return true;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBlueprintPHIDs(array $phids) {
    $this->blueprintPHIDs = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function withBlueprintStates(array $states) {
    $this->blueprintStates = $states;
    return $this;
  }

  public function withObjectStates(array $states) {
    $this->objectStates = $states;
    return $this;
  }

  public function newResultObject() {
    return new DrydockAuthorization();
  }

  protected function willFilterPage(array $authorizations) {
    $blueprint_phids = mpull($authorizations, 'getBlueprintPHID');
    if ($blueprint_phids) {
      $blueprints = id(new DrydockBlueprintQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($blueprint_phids)
        ->execute();
      $blueprints = mpull($blueprints, null, 'getPHID');
    } else {
      $blueprints = array();
    }

    foreach ($authorizations as $key => $authorization) {
      $blueprint = idx($blueprints, $authorization->getBlueprintPHID());
      if (!$blueprint) {
        $this->didRejectResult($authorization);
        unset($authorizations[$key]);
        continue;
      }
      $authorization->attachBlueprint($blueprint);
    }

    $object_phids = mpull($authorizations, 'getObjectPHID');
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

    foreach ($authorizations as $key => $authorization) {
      $object = idx($objects, $authorization->getObjectPHID());
      if (!$object) {
        $this->didRejectResult($authorization);
        unset($authorizations[$key]);
        continue;
      }
      $authorization->attachObject($object);
    }

    return $authorizations;
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

    if ($this->blueprintPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprintPHID IN (%Ls)',
        $this->blueprintPHIDs);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->blueprintStates !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprintAuthorizationState IN (%Ls)',
        $this->blueprintStates);
    }

    if ($this->objectStates !== null) {
      $where[] = qsprintf(
        $conn,
        'objectAuthorizationState IN (%Ls)',
        $this->objectStates);
    }

    return $where;
  }

}
