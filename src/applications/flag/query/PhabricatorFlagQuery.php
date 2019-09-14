<?php

final class PhabricatorFlagQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const GROUP_COLOR = 'color';
  const GROUP_NONE  = 'none';

  private $ids;
  private $ownerPHIDs;
  private $types;
  private $objectPHIDs;
  private $colors;
  private $groupBy = self::GROUP_NONE;

  private $needHandles;
  private $needObjects;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withOwnerPHIDs(array $owner_phids) {
    $this->ownerPHIDs = $owner_phids;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withColors(array $colors) {
    $this->colors = $colors;
    return $this;
  }

  /**
   * NOTE: this is done in PHP and not in MySQL, which means its inappropriate
   * for large datasets. Pragmatically, this is fine for user flags which are
   * typically well under 100 flags per user.
   */
  public function setGroupBy($group) {
    $this->groupBy = $group;
    return $this;
  }

  public function needHandles($need) {
    $this->needHandles = $need;
    return $this;
  }

  public function needObjects($need) {
    $this->needObjects = $need;
    return $this;
  }

  public static function loadUserFlag(PhabricatorUser $user, $object_phid) {
    // Specifying the type in the query allows us to use a key.
    return id(new PhabricatorFlagQuery())
      ->setViewer($user)
      ->withOwnerPHIDs(array($user->getPHID()))
      ->withTypes(array(phid_get_type($object_phid)))
      ->withObjectPHIDs(array($object_phid))
      ->executeOne();
  }

  protected function loadPage() {
    $table = new PhabricatorFlag();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T flag %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $flags) {
    if ($this->needObjects) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(mpull($flags, 'getObjectPHID'))
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
      foreach ($flags as $key => $flag) {
        $object = idx($objects, $flag->getObjectPHID());
        if ($object) {
          $flags[$key]->attachObject($object);
        } else {
          unset($flags[$key]);
        }
      }
    }

    if ($this->needHandles) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(mpull($flags, 'getObjectPHID'))
        ->execute();

      foreach ($flags as $flag) {
        $flag->attachHandle($handles[$flag->getObjectPHID()]);
      }
    }

    switch ($this->groupBy) {
      case self::GROUP_COLOR:
        $flags = msort($flags, 'getColor');
        break;
      case self::GROUP_NONE:
        break;
      default:
        throw new Exception(
          pht('Unknown groupBy parameter: %s', $this->groupBy));
        break;
    }

    return $flags;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'flag.id IN (%Ld)',
        $this->ids);
    }

    if ($this->ownerPHIDs) {
      $where[] = qsprintf(
        $conn,
        'flag.ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn,
        'flag.type IN (%Ls)',
        $this->types);
    }

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn,
        'flag.objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->colors) {
      $where[] = qsprintf(
        $conn,
        'flag.color IN (%Ld)',
        $this->colors);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFlagsApplication';
  }

}
