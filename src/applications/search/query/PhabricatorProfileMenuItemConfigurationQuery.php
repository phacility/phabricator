<?php

final class PhabricatorProfileMenuItemConfigurationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $profilePHIDs;
  private $customPHIDs;
  private $includeGlobal;
  private $affectedObjectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProfilePHIDs(array $phids) {
    $this->profilePHIDs = $phids;
    return $this;
  }

  public function withCustomPHIDs(array $phids, $include_global = false) {
    $this->customPHIDs = $phids;
    $this->includeGlobal = $include_global;
    return $this;
  }

  public function withAffectedObjectPHIDs(array $phids) {
    $this->affectedObjectPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProfileMenuItemConfiguration();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'config.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'config.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->profilePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'config.profilePHID IN (%Ls)',
        $this->profilePHIDs);
    }

    if ($this->customPHIDs !== null) {
      if ($this->customPHIDs && $this->includeGlobal) {
        $where[] = qsprintf(
          $conn,
          'config.customPHID IN (%Ls) OR config.customPHID IS NULL',
          $this->customPHIDs);
      } else if ($this->customPHIDs) {
        $where[] = qsprintf(
          $conn,
          'config.customPHID IN (%Ls)',
          $this->customPHIDs);
      } else {
        $where[] = qsprintf(
          $conn,
          'config.customPHID IS NULL');
      }
    }

    if ($this->affectedObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'affected.dst IN (%Ls)',
        $this->affectedObjectPHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->affectedObjectPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T affected ON affected.src = config.phid
          AND affected.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProfileMenuItemAffectsObjectEdgeType::EDGECONST);
    }

    return $joins;
  }

  protected function willFilterPage(array $page) {
    $items = PhabricatorProfileMenuItem::getAllMenuItems();
    foreach ($page as $key => $item) {
      $item_type = idx($items, $item->getMenuItemKey());
      if (!$item_type) {
        $this->didRejectResult($item);
        unset($page[$key]);
        continue;
      }
      $item_type = clone $item_type;
      $item_type->setViewer($this->getViewer());
      $item->attachMenuItem($item_type);
    }

    if (!$page) {
      return array();
    }

    $profile_phids = mpull($page, 'getProfilePHID');

    $profiles = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($profile_phids)
      ->execute();
    $profiles = mpull($profiles, null, 'getPHID');

    foreach ($page as $key => $item) {
      $profile = idx($profiles, $item->getProfilePHID());
      if (!$profile) {
        $this->didRejectResult($item);
        unset($page[$key]);
        continue;
      }

      $item->attachProfileObject($profile);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'config';
  }

}
