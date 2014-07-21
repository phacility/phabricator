<?php

final class PhrequentTimeSlices extends Phobject {

  private $objectPHID;
  private $isOngoing;
  private $ranges;

  public function __construct($object_phid, $is_ongoing, array $ranges) {
    $this->objectPHID = $object_phid;
    $this->isOngoing = $is_ongoing;
    $this->ranges = $ranges;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function getDuration($now) {
    foreach ($this->ranges as $range) {
      if ($range[1] === null) {
        return $now - $range[0];
      } else {
        return $range[1] - $range[0];
      }
    }
  }

  public function getIsOngoing() {
    return $this->isOngoing;
  }

  public function getRanges() {
    return $this->ranges;
  }

}
