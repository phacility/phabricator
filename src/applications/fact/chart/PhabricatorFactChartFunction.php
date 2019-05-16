<?php

final class PhabricatorFactChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'fact';

  private $fact;
  private $datapoints;

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

    $data = queryfx_all(
      $conn,
      'SELECT value, epoch FROM %T WHERE keyID = %d ORDER BY epoch ASC',
      $table_name,
      $key_id);
    if (!$data) {
      return;
    }

    $points = array();

    $sum = 0;
    foreach ($data as $key => $row) {
      $sum += (int)$row['value'];
      $points[] = array(
        'x' => (int)$row['epoch'],
        'y' => $sum,
      );
    }

    $this->datapoints = $points;
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $points = $this->datapoints;
    if (!$points) {
      return array();
    }

    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();
    $limit = $query->getLimit();

    if ($x_min !== null) {
      foreach ($points as $key => $point) {
        if ($point['x'] < $x_min) {
          unset($points[$key]);
        }
      }
    }

    if ($x_max !== null) {
      foreach ($points as $key => $point) {
        if ($point['x'] > $x_max) {
          unset($points[$key]);
        }
      }
    }

    // If we have too many data points, throw away some of the data.
    if ($limit !== null) {
      $count = count($points);
      if ($count > $limit) {
        $ii = 0;
        $every = ceil($count / $limit);
        foreach ($points as $key => $point) {
          $ii++;
          if (($ii % $every) && ($ii != $count)) {
            unset($points[$key]);
          }
        }
      }
    }

    return $points;
  }

  public function hasDomain() {
    return true;
  }

  public function getDomain() {
    // TODO: We can examine the data to fit a better domain.

    $now = PhabricatorTime::getNow();
    return array($now - phutil_units('90 days in seconds'), $now);
  }

}
