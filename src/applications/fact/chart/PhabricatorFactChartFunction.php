<?php

final class PhabricatorFactChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'fact';

  private $factKey;
  private $fact;
  private $datapoints;

  protected function newArguments(array $arguments) {
    if (count($arguments) !== 1) {
      throw new Exception(
        pht(
          'Chart function "fact(...)" expects one argument, got %s. '.
          'Pass the key for a fact.',
          count($arguments)));
    }

    if (!is_string($arguments[0])) {
      throw new Exception(
        pht(
          'First argument for "fact(...)" is invalid: expected string, '.
          'got %s.',
          phutil_describe_type($arguments[0])));
    }

    $facts = PhabricatorFact::getAllFacts();
    $fact = idx($facts, $arguments[0]);

    if (!$fact) {
      throw new Exception(
        pht(
          'Argument to "fact(...)" is invalid: "%s" is not a known fact '.
          'key.',
          $arguments[0]));
    }

    $this->factKey = $arguments[0];
    $this->fact = $fact;
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

  public function getDatapoints($limit) {
    $points = $this->datapoints;
    if (!$points) {
      return array();
    }

    $axis = $this->getXAxis();
    $x_min = $axis->getMinimumValue();
    $x_max = $axis->getMaximumValue();

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
