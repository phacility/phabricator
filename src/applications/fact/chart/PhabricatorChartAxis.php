<?php

final class PhabricatorChartAxis
  extends Phobject {

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

}
