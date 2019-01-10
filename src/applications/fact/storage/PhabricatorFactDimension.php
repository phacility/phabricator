<?php

abstract class PhabricatorFactDimension extends PhabricatorFactDAO {

  abstract protected function getDimensionColumnName();

  final public function newDimensionID($key, $create = false) {
    $map = $this->newDimensionMap(array($key), $create);
    return idx($map, $key);
  }

  final public function newDimensionUnmap(array $ids) {
    if (!$ids) {
      return array();
    }

    $conn = $this->establishConnection('r');
    $column = $this->getDimensionColumnName();

    $rows = queryfx_all(
      $conn,
      'SELECT id, %C FROM %T WHERE id IN (%Ld)',
      $column,
      $this->getTableName(),
      $ids);
    $rows = ipull($rows, $column, 'id');

    return $rows;
  }

  final public function newDimensionMap(array $keys, $create = false) {
    if (!$keys) {
      return array();
    }

    $conn = $this->establishConnection('r');
    $column = $this->getDimensionColumnName();

    $rows = queryfx_all(
      $conn,
      'SELECT id, %C FROM %T WHERE %C IN (%Ls)',
      $column,
      $this->getTableName(),
      $column,
      $keys);
    $rows = ipull($rows, 'id', $column);

    $map = array();
    $need = array();
    foreach ($keys as $key) {
      if (isset($rows[$key])) {
        $map[$key] = (int)$rows[$key];
      } else {
        $need[] = $key;
      }
    }

    if (!$need) {
      return $map;
    }

    if (!$create) {
      return $map;
    }

    $sql = array();
    foreach ($need as $key) {
      $sql[] = qsprintf(
        $conn,
        '(%s)',
        $key);
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT IGNORE INTO %T (%C) VALUES %LQ',
          $this->getTableName(),
          $column,
          $chunk);
      }
    unset($unguarded);

    $rows = queryfx_all(
      $conn,
      'SELECT id, %C FROM %T WHERE %C IN (%Ls)',
      $column,
      $this->getTableName(),
      $column,
      $need);
    $rows = ipull($rows, 'id', $column);

    foreach ($need as $key) {
      if (isset($rows[$key])) {
        $map[$key] = (int)$rows[$key];
      } else {
        throw new Exception(
          pht(
            'Failed to load or generate dimension ID ("%s") for dimension '.
            'key "%s".',
            get_class($this),
            $key));
      }
    }

    return $map;
  }

}
