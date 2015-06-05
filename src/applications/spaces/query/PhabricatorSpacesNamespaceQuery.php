<?php

final class PhabricatorSpacesNamespaceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const KEY_ALL = 'spaces.all';
  const KEY_DEFAULT = 'spaces.default';

  private $ids;
  private $phids;
  private $isDefaultNamespace;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIsDefaultNamespace($default) {
    $this->isDefaultNamespace = $default;
    return $this;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSpacesApplication';
  }

  protected function loadPage() {
    $table = new PhabricatorSpacesNamespace();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
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

    if ($this->isDefaultNamespace !== null) {
      if ($this->isDefaultNamespace) {
        $where[] = qsprintf(
          $conn_r,
          'isDefaultNamespace = 1');
      } else {
        $where[] = qsprintf(
          $conn_r,
          'isDefaultNamespace IS NULL');
      }
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  public static function destroySpacesCache() {
    $cache = PhabricatorCaches::getRequestCache();
    $cache->deleteKeys(
      array(
        self::KEY_ALL,
        self::KEY_DEFAULT,
      ));
  }

  public static function getAllSpaces() {
    $cache = PhabricatorCaches::getRequestCache();
    $cache_key = self::KEY_ALL;

    $spaces = $cache->getKey($cache_key);
    if ($spaces === null) {
      $spaces = id(new PhabricatorSpacesNamespaceQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->execute();
      $spaces = mpull($spaces, null, 'getPHID');
      $cache->setKey($cache_key, $spaces);
    }

    return $spaces;
  }

  public static function getDefaultSpace() {
    $cache = PhabricatorCaches::getRequestCache();
    $cache_key = self::KEY_DEFAULT;

    $default_space = $cache->getKey($cache_key, false);
    if ($default_space === false) {
      $default_space = null;

      $spaces = self::getAllSpaces();
      foreach ($spaces as $space) {
        if ($space->getIsDefaultNamespace()) {
          $default_space = $space;
          break;
        }
      }

      $cache->setKey($cache_key, $default_space);
    }

    return $default_space;
  }

  public static function getViewerSpaces(PhabricatorUser $viewer) {
    $spaces = self::getAllSpaces();

    $result = array();
    foreach ($spaces as $key => $space) {
      $can_see = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $space,
        PhabricatorPolicyCapability::CAN_VIEW);
      if ($can_see) {
        $result[$key] = $space;
      }
    }

    return $result;
  }

}
