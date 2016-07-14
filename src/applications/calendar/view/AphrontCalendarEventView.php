<?php

final class AphrontCalendarEventView extends AphrontView {

  private $hostPHID;
  private $name;
  private $epochStart;
  private $epochEnd;
  private $description;
  private $eventID;
  private $viewerIsInvited;
  private $uri;
  private $isAllDay;
  private $icon;
  private $iconColor;
  private $canEdit;
  private $isCancelled;

  public function setIconColor($icon_color) {
    $this->iconColor = $icon_color;
    return $this;
  }

  public function getIconColor() {
    return $this->iconColor;
  }

  public function setIsCancelled($is_cancelled) {
    $this->isCancelled = $is_cancelled;
    return $this;
  }

  public function getIsCancelled() {
    return $this->isCancelled;
  }

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

  public function setHostPHID($host_phid) {
    $this->hostPHID = $host_phid;
    return $this;
  }

  public function getHostPHID() {
    return $this->hostPHID;
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
