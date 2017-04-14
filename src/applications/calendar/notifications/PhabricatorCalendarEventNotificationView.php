<?php

final class PhabricatorCalendarEventNotificationView
  extends Phobject {

  private $viewer;
  private $event;
  private $epoch;
  private $dateTime;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setEvent(PhabricatorCalendarEvent $event) {
    $this->event = $event;
    return $this;
  }

  public function getEvent() {
    return $this->event;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function getEpoch() {
    return $this->epoch;
  }

  public function setDateTime(PhutilCalendarDateTime $date_time) {
    $this->dateTime = $date_time;
    return $this;
  }

  public function getDateTime() {
    return $this->dateTime;
  }

  public function getDisplayMinutes() {
    $epoch = $this->getEpoch();
    $now = PhabricatorTime::getNow();
    $minutes = (int)ceil(($epoch - $now) / 60);
    return new PhutilNumber($minutes);
  }

  public function getDisplayTime() {
    $viewer = $this->getViewer();

    $epoch = $this->getEpoch();
    return phabricator_datetime($epoch, $viewer);
  }

  public function getDisplayTimeWithTimezone() {
    $viewer = $this->getViewer();

    $epoch = $this->getEpoch();
    return phabricator_datetimezone($epoch, $viewer);
  }


}
