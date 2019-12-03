<?php

final class PhabricatorFactChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'fact';

  private $fact;
  private $map;
  private $refs;

  protected function newArguments() {
    $key_argument = $this->newArgument()
      ->setName('fact-key')
      ->setType('fact-key');

    $parser = $this->getArgumentParser();
    $parser->parseArgument($key_argument);

    $fact = $this->getArgument('fact-key');
    $this->fact = $fact;

    return $fact->getFunctionArguments();
  }

  public function loadData() {
    $fact = $this->fact;

    $key_id = id(new PhabricatorFactKeyDimension())
      ->newDimensionID($fact->getKey());
    if (!$key_id) {
      $this->map = array();
      return;
    }

    $table = $fact->newDatapoint();
    $conn = $table->establishConnection('r');
    $table_name = $table->getTableName();

    $where = array();

    $where[] = qsprintf(
      $conn,
      'keyID = %d',
      $key_id);

    $parser = $this->getArgumentParser();

    $parts = $fact->buildWhereClauseParts($conn, $parser);
    foreach ($parts as $part) {
      $where[] = $part;
    }

    $data = queryfx_all(
      $conn,
      'SELECT id, value, epoch FROM %T WHERE %LA ORDER BY epoch ASC',
      $table_name,
      $where);

    $map = array();
    $refs = array();
    if ($data) {
      foreach ($data as $row) {
        $ref = (string)$row['id'];
        $value = (int)$row['value'];
        $epoch = (int)$row['epoch'];

        if (!isset($map[$epoch])) {
          $map[$epoch] = 0;
        }

        $map[$epoch] += $value;

        if (!isset($refs[$epoch])) {
          $refs[$epoch] = array();
        }

        $refs[$epoch][] = $ref;
      }
    }

    $this->map = $map;
    $this->refs = $refs;
  }

  public function getDomain() {
    $min = head_key($this->map);
    $max = last_key($this->map);

    return new PhabricatorChartInterval($min, $max);
  }

  public function newInputValues(PhabricatorChartDataQuery $query) {
    return array_keys($this->map);
  }

  public function evaluateFunction(array $xv) {
    $map = $this->map;

    $yv = array();

    foreach ($xv as $x) {
      if (isset($map[$x])) {
        $yv[] = $map[$x];
      } else {
        $yv[] = null;
      }
    }

    return $yv;
  }

  public function getDataRefs(array $xv) {
    return array_select_keys($this->refs, $xv);
  }

  public function loadRefs(array $refs) {
    $fact = $this->fact;

    $datapoint_table = $fact->newDatapoint();
    $conn = $datapoint_table->establishConnection('r');

    $dimension_table = new PhabricatorFactObjectDimension();

    $where = array();

    $where[] = qsprintf(
      $conn,
      'p.id IN (%Ld)',
      $refs);


    $rows = queryfx_all(
      $conn,
      'SELECT
          p.id id,
          p.value,
          od.objectPHID objectPHID,
          dd.objectPHID dimensionPHID
        FROM %R p
          LEFT JOIN %R od ON od.id = p.objectID
          LEFT JOIN %R dd ON dd.id = p.dimensionID
          WHERE %LA',
      $datapoint_table,
      $dimension_table,
      $dimension_table,
      $where);
    $rows = ipull($rows, null, 'id');

    $results = array();

    foreach ($refs as $ref) {
      if (!isset($rows[$ref])) {
        continue;
      }

      $row = $rows[$ref];

      $results[$ref] = array(
        'objectPHID' => $row['objectPHID'],
        'dimensionPHID' => $row['dimensionPHID'],
        'value' => (float)$row['value'],
      );
    }

    return $results;
  }

}
