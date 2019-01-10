<?php

final class PhabricatorFactDatapointQuery extends Phobject {

  private $facts;
  private $objectPHIDs;
  private $limit;

  private $needVectors;

  private $keyMap = array();
  private $dimensionMap = array();

  public function withFacts(array $facts) {
    $this->facts = $facts;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function needVectors($need) {
    $this->needVectors = $need;
    return $this;
  }

  public function execute() {
    $facts = mpull($this->facts, null, 'getKey');
    if (!$facts) {
      throw new Exception(pht('Executing a fact query requires facts.'));
    }

    $table_map = array();
    foreach ($facts as $fact) {
      $datapoint = $fact->newDatapoint();
      $table = $datapoint->getTableName();

      if (!isset($table_map[$table])) {
        $table_map[$table] = array(
          'table' => $datapoint,
          'facts' => array(),
        );
      }

      $table_map[$table]['facts'][] = $fact;
    }

    $rows = array();
    foreach ($table_map as $spec) {
      $rows[] = $this->executeWithTable($spec);
    }
    $rows = array_mergev($rows);

    $key_unmap = array_flip($this->keyMap);
    $dimension_unmap = array_flip($this->dimensionMap);

    $groups = array();
    $need_phids = array();
    foreach ($rows as $row) {
      $groups[$row['keyID']][] = $row;

      $object_id = $row['objectID'];
      if (!isset($dimension_unmap[$object_id])) {
        $need_phids[$object_id] = $object_id;
      }

      $dimension_id = $row['dimensionID'];
      if ($dimension_id && !isset($dimension_unmap[$dimension_id])) {
        $need_phids[$dimension_id] = $dimension_id;
      }
    }

    $dimension_unmap += id(new PhabricatorFactObjectDimension())
      ->newDimensionUnmap($need_phids);

    $results = array();
    foreach ($groups as $key_id => $rows) {
      $key = $key_unmap[$key_id];
      $fact = $facts[$key];
      $datapoint = $fact->newDatapoint();
      foreach ($rows as $row) {
        $dimension_id = $row['dimensionID'];
        if ($dimension_id) {
          if (!isset($dimension_unmap[$dimension_id])) {
            continue;
          } else {
            $dimension_phid = $dimension_unmap[$dimension_id];
          }
        } else {
          $dimension_phid = null;
        }

        $object_id = $row['objectID'];
        if (!isset($dimension_unmap[$object_id])) {
          continue;
        } else {
          $object_phid = $dimension_unmap[$object_id];
        }

        $result = array(
          'key' => $key,
          'objectPHID' => $object_phid,
          'dimensionPHID' => $dimension_phid,
          'value' => (int)$row['value'],
          'epoch' => $row['epoch'],
        );

        if ($this->needVectors) {
          $result['vector'] = $datapoint->newRawVector($result);
        }

        $results[] = $result;
      }
    }

    return $results;
  }

  private function executeWithTable(array $spec) {
    $table = $spec['table'];
    $facts = $spec['facts'];
    $conn = $table->establishConnection('r');

    $fact_keys = mpull($facts, 'getKey');
    $this->keyMap = id(new PhabricatorFactKeyDimension())
      ->newDimensionMap($fact_keys);

    if (!$this->keyMap) {
      return array();
    }

    $where = array();

    $where[] = qsprintf(
      $conn,
      'keyID IN (%Ld)',
      $this->keyMap);

    if ($this->objectPHIDs) {
      $object_map = id(new PhabricatorFactObjectDimension())
        ->newDimensionMap($this->objectPHIDs);
      if (!$object_map) {
        return array();
      }

      $this->dimensionMap = $object_map;

      $where[] = qsprintf(
        $conn,
        'objectID IN (%Ld)',
        $this->dimensionMap);
    }

    $where = qsprintf($conn, '%LA', $where);

    if ($this->limit) {
      $limit = qsprintf(
        $conn,
        'LIMIT %d',
        $this->limit);
    } else {
      $limit = qsprintf($conn, '');
    }

    return queryfx_all(
      $conn,
      'SELECT keyID, objectID, dimensionID, value, epoch
        FROM %T WHERE %Q %Q',
      $table->getTableName(),
      $where,
      $limit);
  }

}
