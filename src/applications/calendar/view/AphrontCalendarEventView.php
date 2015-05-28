<?php

final class AphrontCalendarEventView extends AphrontView {

  private $userPHID;
  private $name;
  private $epochStart;
  private $epochEnd;
  private $description;
  private $eventID;
  private $viewerIsInvited;
  private $uri;
  private $isAllDay;
  private $icon;
  private $canEdit;

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setEventID($event_id) {
    $this->eventID = $event_id;
    return $this;
  }
  public function getEventID() {
    return $this->eventID;
  }

  public function setViewerIsInvited($viewer_is_invited) {
    $this->viewerIsInvited = $viewer_is_invited;
    return $this;
  }
  public function getViewerIsInvited() {
    return $this->viewerIsInvited;
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

  public function setIsAllDay($is_all_day) {
    $this->isAllDay = $is_all_day;
    return $this;
  }

  public function getIsAllDay() {
    return $this->isAllDay;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function getCanEdit() {
    return $this->canEdit;
  }

  public function getMultiDay() {
    $nextday = strtotime('12:00 AM Tomorrow', $this->getEpochStart());
    if ($this->getEpochEnd() > $nextday) {
      return true;
    }
    return false;
  }

  public function render() {
    throw new Exception(pht('Events are only rendered indirectly.'));
  }

}
