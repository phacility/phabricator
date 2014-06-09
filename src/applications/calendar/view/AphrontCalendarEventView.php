<?php

final class AphrontCalendarEventView extends AphrontView {

  private $userPHID;
  private $name;
  private $epochStart;
  private $epochEnd;
  private $description;
  private $eventID;
  private $color;

  public function setEventID($event_id) {
    $this->eventID = $event_id;
    return $this;
  }
  public function getEventID() {
    return $this->eventID;
  }

  public function setUserPHID($user_phid) {
    $this->userPHID = $user_phid;
    return $this;
  }

  public function getUserPHID() {
    return $this->userPHID;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
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

  public function getName() {
    return $this->name;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }
  public function getColor() {
    if ($this->color) {
      return $this->color;
    } else {
      return CalendarColors::COLOR_SKY;
    }
  }

  public function getAllDay() {
    $time = (60 * 60 * 22);
    if (($this->getEpochEnd() - $this->getEpochStart()) >= $time) {
      return true;
    }
    return false;
  }

  public function getMultiDay() {
    $nextday = strtotime('12:00 AM Tomorrow', $this->getEpochStart());
    if ($this->getEpochEnd() > $nextday) {
      return true;
    }
    return false;
  }

  public function render() {
    throw new Exception('Events are only rendered indirectly.');
  }

}
