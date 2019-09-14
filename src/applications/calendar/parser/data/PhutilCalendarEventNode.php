<?php

final class PhutilCalendarEventNode
  extends PhutilCalendarContainerNode {

  const NODETYPE = 'event';

  private $uid;
  private $name;
  private $description;
  private $startDateTime;
  private $endDateTime;
  private $duration;
  private $createdDateTime;
  private $modifiedDateTime;
  private $organizer;
  private $attendees = array();
  private $recurrenceRule;
  private $recurrenceExceptions = array();
  private $recurrenceDates = array();
  private $recurrenceID;

  public function setUID($uid) {
    $this->uid = $uid;
    return $this;
  }

  public function getUID() {
    return $this->uid;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
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

  public function setStartDateTime(PhutilCalendarDateTime $start) {
    $this->startDateTime = $start;
    return $this;
  }

  public function getStartDateTime() {
    return $this->startDateTime;
  }

  public function setEndDateTime(PhutilCalendarDateTime $end) {
    $this->endDateTime = $end;
    return $this;
  }

  public function getEndDateTime() {
    $end = $this->endDateTime;
    if ($end) {
      return $end;
    }

    $start = $this->getStartDateTime();
    $duration = $this->getDuration();
    if ($start && $duration) {
      return id(new PhutilCalendarRelativeDateTime())
        ->setOrigin($start)
        ->setDuration($duration);
    }

    // If no end date or duration are specified, the event is instantaneous.
    return $start;
  }

  public function setDuration(PhutilCalendarDuration $duration) {
    $this->duration = $duration;
    return $this;
  }

  public function getDuration() {
    return $this->duration;
  }

  public function setCreatedDateTime(PhutilCalendarDateTime $created) {
    $this->createdDateTime = $created;
    return $this;
  }

  public function getCreatedDateTime() {
    return $this->createdDateTime;
  }

  public function setModifiedDateTime(PhutilCalendarDateTime $modified) {
    $this->modifiedDateTime = $modified;
    return $this;
  }

  public function getModifiedDateTime() {
    return $this->modifiedDateTime;
  }

  public function setOrganizer(PhutilCalendarUserNode $organizer) {
    $this->organizer = $organizer;
    return $this;
  }

  public function getOrganizer() {
    return $this->organizer;
  }

  public function setAttendees(array $attendees) {
    assert_instances_of($attendees, 'PhutilCalendarUserNode');
    $this->attendees = $attendees;
    return $this;
  }

  public function getAttendees() {
    return $this->attendees;
  }

  public function addAttendee(PhutilCalendarUserNode $attendee) {
    $this->attendees[] = $attendee;
    return $this;
  }

  public function setRecurrenceRule(
    PhutilCalendarRecurrenceRule $recurrence_rule) {
    $this->recurrenceRule = $recurrence_rule;
    return $this;
  }

  public function getRecurrenceRule() {
    return $this->recurrenceRule;
  }

  public function setRecurrenceExceptions(array $recurrence_exceptions) {
    assert_instances_of($recurrence_exceptions, 'PhutilCalendarDateTime');
    $this->recurrenceExceptions = $recurrence_exceptions;
    return $this;
  }

  public function getRecurrenceExceptions() {
    return $this->recurrenceExceptions;
  }

  public function setRecurrenceDates(array $recurrence_dates) {
    assert_instances_of($recurrence_dates, 'PhutilCalendarDateTime');
    $this->recurrenceDates = $recurrence_dates;
    return $this;
  }

  public function getRecurrenceDates() {
    return $this->recurrenceDates;
  }

  public function setRecurrenceID($recurrence_id) {
    $this->recurrenceID = $recurrence_id;
    return $this;
  }

  public function getRecurrenceID() {
    return $this->recurrenceID;
  }

}
