<?php

final class AphrontCalendarEventView extends AphrontView {

  private $userPHID;
  private $name;
  private $epochStart;
  private $epochEnd;
  private $description;
  private $eventID;

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

  public function render() {
    throw new Exception("Events are only rendered indirectly.");
  }

}
