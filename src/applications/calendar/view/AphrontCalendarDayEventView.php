<?php

final class AphrontCalendarDayEventView extends AphrontView {

  private $event;
  private $epochStart;
  private $epochEnd;
  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setEpochRange($start, $end) {
    $this->epochStart = $start;
    $this->epochEnd   = $end;
    return $this;
  }

  public function getEpochStart() {
    return $this->epochStart;
  }

  public function getEpochEnd() {
    return $this->epochEnd;
  }

  public function render() {
    $box = new PHUIObjectBoxView();
    $box->setHeaderText($this->name);
    return $box;

  }
}
