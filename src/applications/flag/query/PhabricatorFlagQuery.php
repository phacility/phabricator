<?php

final class PhabricatorFlagQuery {

  private $ownerPHIDs;
  private $types;
  private $objectPHIDs;

  private $limit;
  private $offset;

  private $needHandles;
  private $needObjects;
  private $viewer;

  public function setViewer($viewer) {
    $this->viewer = $viewer;
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

  public function needHandles($need) {
    $this->needHandles = $need;
    return $this;
  }

  public function needObjects($need) {
    $this->needObjects = $need;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public static function loadUserFlag(PhabricatorUser $user, $object_phid) {
    // Specifying the type in the query allows us to use a key.
    return id(new PhabricatorFlag())->loadOneWhere(
      'ownerPHID = %s AND type = %s AND objectPHID = %s',
      $user->getPHID(),
      phid_get_type($object_phid),
      $object_phid);
  }


  public function execute() {
    $table = new PhabricatorFlag();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);
    $order = $this->buildOrderClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T flag %Q %Q %Q',
      $table->getTableName(),
      $where,
      $order,
      $limit);

    $flags = $table->loadAllFromArray($data);

    if ($this->needHandles || $this->needObjects) {
      $phids = ipull($data, 'objectPHID');
      $query = new PhabricatorObjectHandleData($phids);
      $query->setViewer($this->viewer);

      if ($this->needHandles) {
        $handles = $query->loadHandles();
        foreach ($flags as $flag) {
          $handle = idx($handles, $flag->getObjectPHID());
          if ($handle) {
            $flag->attachHandle($handle);
          }
        }
      }

      if ($this->needObjects) {
        $objects = $query->loadObjects();
        foreach ($flags as $flag) {
          $object = idx($objects, $flag->getObjectPHID());
          if ($object) {
            $flag->attachObject($object);
          }
        }
      }
    }

    return $flags;
  }

  private function buildWhereClause($conn_r) {

    $where = array();

    if ($this->ownerPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'flag.ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn_r,
        'flag.type IN (%Ls)',
        $this->types);
    }

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'flag.objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($where) {
      return 'WHERE ('.implode(') AND (', $where).')';
    } else {
      return '';
    }
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY id DESC';
  }

  private function buildLimitClause($conn_r) {
    if ($this->limit && $this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, $this->limit);
    } else if ($this->limit) {
      return qsprintf($conn_r, 'LIMIT %d', $this->limit);
    } else if ($this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, PHP_INT_MAX);
    } else {
      return '';
    }
  }

}
