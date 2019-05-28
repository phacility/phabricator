<?php

final class PhabricatorChartInterval
  extends Phobject {

  private $min;
  private $max;

  public function __construct($min, $max) {
    $this->min = $min;
    $this->max = $max;
  }

  public static function newFromIntervalList(array $intervals) {
    $min = null;
    $max = null;
    foreach ($intervals as $interval) {
      if ($interval === null) {
        continue;
      }

      $interval_min = $interval->getMin();
      if ($interval_min !== null) {
        if ($min === null) {
          $min = $interval_min;
        } else {
          $min = min($min, $interval_min);
        }
      }

      $interval_max = $interval->getMax();
      if ($interval_max !== null) {
        if ($max === null) {
          $max = $interval_max;
        } else {
          $max = max($max, $interval_max);
        }
      }
    }

    return new self($min, $max);
  }

  public function setMin($min) {
    $this->min = $min;
    return $this;
  }

  public function getMin() {
    return $this->min;
  }

  public function setMax($max) {
    $this->max = $max;
    return $this;
  }

  public function getMax() {
    return $this->max;
  }

}
