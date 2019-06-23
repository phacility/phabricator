<?php

final class PhabricatorFactChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'fact';

  private $fact;
  private $map;

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
      'SELECT value, epoch FROM %T WHERE %LA ORDER BY epoch ASC',
      $table_name,
      $where);

    $map = array();
    if ($data) {
      foreach ($data as $row) {
        $value = (int)$row['value'];
        $epoch = (int)$row['epoch'];

        if (!isset($map[$epoch])) {
          $map[$epoch] = 0;
        }

        $map[$epoch] += $value;
      }
    }

    $this->map = $map;
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

}
