<?php

final class PhabricatorSpacesNamespaceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const KEY_ALL = 'spaces.all';
  const KEY_DEFAULT = 'spaces.default';

  private $ids;
  private $phids;
  private $isDefaultNamespace;
  private $isArchived;

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

  public function withIsArchived($archived) {
    $this->isArchived = $archived;
    return $this;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSpacesApplication';
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PhabricatorSpacesNamespace());
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

    if ($this->isDefaultNamespace !== null) {
      if ($this->isDefaultNamespace) {
        $where[] = qsprintf(
          $conn,
          'isDefaultNamespace = 1');
      } else {
        $where[] = qsprintf(
          $conn,
          'isDefaultNamespace IS NULL');
      }
    }

    if ($this->isArchived !== null) {
      $where[] = qsprintf(
        $conn,
        'isArchived = %d',
        (int)$this->isArchived);
    }

    return $where;
  }

  public static function destroySpacesCache() {
    $cache = PhabricatorCaches::getRequestCache();
    $cache->deleteKeys(
      array(
        self::KEY_ALL,
        self::KEY_DEFAULT,
      ));
  }

  public static function getSpacesExist() {
    return (bool)self::getAllSpaces();
  }

  public static function getViewerSpacesExist(PhabricatorUser $viewer) {
    if (!self::getSpacesExist()) {
      return false;
    }

    // If the viewer has access to only one space, pretend spaces simply don't
    // exist.
    $spaces = self::getViewerSpaces($viewer);
    return (count($spaces) > 1);
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


  public static function getViewerActiveSpaces(PhabricatorUser $viewer) {
    $spaces = self::getViewerSpaces($viewer);

    foreach ($spaces as $key => $space) {
      if ($space->getIsArchived()) {
        unset($spaces[$key]);
      }
    }

    return $spaces;
  }

  public static function getSpaceOptionsForViewer(
    PhabricatorUser $viewer,
    $space_phid) {

    $viewer_spaces = self::getViewerSpaces($viewer);

    $map = array();
    foreach ($viewer_spaces as $space) {

      // Skip archived spaces, unless the object is already in that space.
      if ($space->getIsArchived()) {
        if ($space->getPHID() != $space_phid) {
          continue;
        }
      }

      $map[$space->getPHID()] = pht(
        'Space %s: %s',
        $space->getMonogram(),
        $space->getNamespaceName());
    }
    asort($map);

    return $map;
  }


  /**
   * Get the Space PHID for an object, if one exists.
   *
   * This is intended to simplify performing a bunch of redundant checks; you
   * can intentionally pass any value in (including `null`).
   *
   * @param wild
   * @return phid|null
   */
  public static function getObjectSpacePHID($object) {
    if (!$object) {
      return null;
    }

    if (!($object instanceof PhabricatorSpacesInterface)) {
      return null;
    }

    $space_phid = $object->getSpacePHID();
    if ($space_phid === null) {
      $default_space = self::getDefaultSpace();
      if ($default_space) {
        $space_phid = $default_space->getPHID();
      }
    }

    return $space_phid;
  }

}
