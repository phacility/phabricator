<?php

final class PhabricatorChartDataQuery
  extends Phobject {

  private $limit;
  private $minimumValue;
  private $maximumValue;

  public function setMinimumValue($minimum_value) {
    $this->minimumValue = $minimum_value;
    return $this;
  }

  public function getMinimumValue() {
    return $this->minimumValue;
  }

  public function setMaximumValue($maximum_value) {
    $this->maximumValue = $maximum_value;
    return $this;
  }

  public function getMaximumValue() {
    return $this->maximumValue;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
  }

}
