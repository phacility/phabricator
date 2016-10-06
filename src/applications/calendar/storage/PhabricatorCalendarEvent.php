<?php

final class PhabricatorCalendarEvent extends PhabricatorCalendarDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorProjectInterface,
    PhabricatorMarkupInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorDestructibleInterface,
    PhabricatorMentionableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorSpacesInterface,
    PhabricatorFulltextInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $hostPHID;
  protected $description;
  protected $isCancelled;
  protected $isAllDay;
  protected $icon;
  protected $mailKey;
  protected $isStub;

  protected $isRecurring = 0;

  private $isGhostEvent = false;
  protected $instanceOfEventPHID;
  protected $sequenceIndex;

  protected $viewPolicy;
  protected $editPolicy;

  protected $spacePHID;

  protected $utcInitialEpoch;
  protected $utcUntilEpoch;
  protected $utcInstanceEpoch;
  protected $parameters = array();

  private $parentEvent = self::ATTACHABLE;
  private $invitees = self::ATTACHABLE;

  private $viewerTimezone;

  // TODO: DEPRECATED. Remove once we're sure the migrations worked.
  protected $allDayDateFrom;
  protected $allDayDateTo;
  protected $dateFrom;
  protected $dateTo;
  protected $recurrenceEndDate;
  protected $recurrenceFrequency = array();

  public static function initializeNewCalendarEvent(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorCalendarApplication'))
      ->executeOne();

    $view_default = PhabricatorCalendarEventDefaultViewCapability::CAPABILITY;
    $edit_default = PhabricatorCalendarEventDefaultEditCapability::CAPABILITY;
    $view_policy = $app->getPolicy($view_default);
    $edit_policy = $app->getPolicy($edit_default);

    $now = PhabricatorTime::getNow();

    $default_icon = 'fa-calendar';

    $datetime_start = PhutilCalendarAbsoluteDateTime::newFromEpoch(
      $now,
      $actor->getTimezoneIdentifier());
    $datetime_end = $datetime_start->newRelativeDateTime('PT1H');

    return id(new PhabricatorCalendarEvent())
      ->setHostPHID($actor->getPHID())
      ->setIsCancelled(0)
      ->setIsAllDay(0)
      ->setIsStub(0)
      ->setIsRecurring(0)
      ->setIcon($default_icon)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID())
      ->attachInvitees(array())
      ->setDateFrom(0)
      ->setDateTo(0)
      ->setAllDayDateFrom(0)
      ->setAllDayDateTo(0)
      ->setStartDateTime($datetime_start)
      ->setEndDateTime($datetime_end)
      ->applyViewerTimezone($actor);
  }

  private function newChild(
    PhabricatorUser $actor,
    $sequence,
    PhutilCalendarDateTime $start = null) {
    if (!$this->isParentEvent()) {
      throw new Exception(
        pht(
          'Unable to generate a new child event for an event which is not '.
          'a recurring parent event!'));
    }

    $child = id(new self())
      ->setIsCancelled(0)
      ->setIsStub(0)
      ->setInstanceOfEventPHID($this->getPHID())
      ->setSequenceIndex($sequence)
      ->setIsRecurring(true)
      ->attachParentEvent($this)
      ->setAllDayDateFrom(0)
      ->setAllDayDateTo(0)
      ->setDateFrom(0)
      ->setDateTo(0);

    return $child->copyFromParent($actor, $start);
  }

  protected function readField($field) {
    static $inherit = array(
      'hostPHID' => true,
      'isAllDay' => true,
      'icon' => true,
      'spacePHID' => true,
      'viewPolicy' => true,
      'editPolicy' => true,
      'name' => true,
      'description' => true,
    );

    // Read these fields from the parent event instead of this event. For
    // example, we want any changes to the parent event's name to apply to
    // the child.
    if (isset($inherit[$field])) {
      if ($this->getIsStub()) {
        // TODO: This should be unconditional, but the execution order of
        // CalendarEventQuery and applyViewerTimezone() are currently odd.
        if ($this->parentEvent !== self::ATTACHABLE) {
          return $this->getParentEvent()->readField($field);
        }
      }
    }

    return parent::readField($field);
  }


  public function copyFromParent(
    PhabricatorUser $actor,
    PhutilCalendarDateTime $start = null) {

    if (!$this->isChildEvent()) {
      throw new Exception(
        pht(
          'Unable to copy from parent event: this is not a child event.'));
    }

    $parent = $this->getParentEvent();

    $this
      ->setHostPHID($parent->getHostPHID())
      ->setIsAllDay($parent->getIsAllDay())
      ->setIcon($parent->getIcon())
      ->setSpacePHID($parent->getSpacePHID())
      ->setViewPolicy($parent->getViewPolicy())
      ->setEditPolicy($parent->getEditPolicy())
      ->setName($parent->getName())
      ->setDescription($parent->getDescription());

    $sequence = $this->getSequenceIndex();

    if ($start) {
      $start_datetime = $start;
    } else {
      $start_datetime = $parent->newSequenceIndexDateTime($sequence);

      if (!$start_datetime) {
        throw new Exception(
          pht(
            'Sequence "%s" is not valid for event!',
            $sequence));
      }
    }

    $duration = $parent->newDuration();
    $end_datetime = $start_datetime->newRelativeDateTime($duration);

    $this
      ->setStartDateTime($start_datetime)
      ->setEndDateTime($end_datetime);

    return $this;
  }

  public function isValidSequenceIndex(PhabricatorUser $viewer, $sequence) {
    return (bool)$this->newSequenceIndexDateTime($sequence);
  }

  public function newSequenceIndexDateTime($sequence) {
    $set = $this->newRecurrenceSet();
    if (!$set) {
      return null;
    }

    $instances = $set->getEventsBetween(
      null,
      $this->newUntilDateTime(),
      $sequence + 1);

    return idx($instances, $sequence, null);
  }

  public function newStub(PhabricatorUser $actor, $sequence) {
    $stub = $this->newChild($actor, $sequence);

    $stub->setIsStub(1);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $stub->save();
    unset($unguarded);

    $stub->applyViewerTimezone($actor);

    return $stub;
  }

  public function newGhost(
    PhabricatorUser $actor,
    $sequence,
    PhutilCalendarDateTime $start = null) {

    $ghost = $this->newChild($actor, $sequence, $start);

    $ghost
      ->setIsGhostEvent(true)
      ->makeEphemeral();

    $ghost->applyViewerTimezone($actor);

    return $ghost;
  }

  public function applyViewerTimezone(PhabricatorUser $viewer) {
    $this->viewerTimezone = $viewer->getTimezoneIdentifier();
    return $this;
  }

  public function getDuration() {
    return ($this->getEndDateTimeEpoch() - $this->getStartDateTimeEpoch());
  }

  public function updateUTCEpochs() {
    // The "intitial" epoch is the start time of the event, in UTC.
    $start_date = $this->newStartDateTime()
      ->setViewerTimezone('UTC');
    $start_epoch = $start_date->getEpoch();
    $this->setUTCInitialEpoch($start_epoch);

    // The "until" epoch is the last UTC epoch on which any instance of this
    // event occurs. For infinitely recurring events, it is `null`.

    if (!$this->getIsRecurring()) {
      $end_date = $this->newEndDateTime()
        ->setViewerTimezone('UTC');
      $until_epoch = $end_date->getEpoch();
    } else {
      $until_epoch = null;
      $until_date = $this->newUntilDateTime();
      if ($until_date) {
        $until_date->setViewerTimezone('UTC');
        $duration = $this->newDuration();
        $until_epoch = id(new PhutilCalendarRelativeDateTime())
          ->setOrigin($until_date)
          ->setDuration($duration)
          ->getEpoch();
      }
    }
    $this->setUTCUntilEpoch($until_epoch);

    // The "instance" epoch is a property of instances of recurring events.
    // It's the original UTC epoch on which the instance started. Usually that
    // is the same as the start date, but they may be different if the instance
    // has been edited.

    // The ICS format uses this value (original start time) to identify event
    // instances, and must do so because it allows additional arbitrary
    // instances to be added (with "RDATE").

    $instance_epoch = null;
    $instance_date = $this->newInstanceDateTime();
    if ($instance_date) {
      $instance_epoch = $instance_date
        ->setViewerTimezone('UTC')
        ->getEpoch();
    }
    $this->setUTCInstanceEpoch($instance_epoch);

    return $this;
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    $this->updateUTCEpochs();

    return parent::save();
  }

  /**
   * Get the event start epoch for evaluating invitee availability.
   *
   * When assessing availability, we pretend events start earlier than they
   * really do. This allows us to mark users away for the entire duration of a
   * series of back-to-back meetings, even if they don't strictly overlap.
   *
   * @return int Event start date for availability caches.
   */
  public function getStartDateTimeEpochForCache() {
    $epoch = $this->getStartDateTimeEpoch();
    $window = phutil_units('15 minutes in seconds');
    return ($epoch - $window);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'description' => 'text',
        'isCancelled' => 'bool',
        'isAllDay' => 'bool',
        'icon' => 'text32',
        'mailKey' => 'bytes20',
        'isRecurring' => 'bool',
        'instanceOfEventPHID' => 'phid?',
        'sequenceIndex' => 'uint32?',
        'isStub' => 'bool',
        'utcInitialEpoch' => 'epoch',
        'utcUntilEpoch' => 'epoch?',
        'utcInstanceEpoch' => 'epoch?',

        // TODO: DEPRECATED.
        'allDayDateFrom' => 'epoch',
        'allDayDateTo' => 'epoch',
        'dateFrom' => 'epoch',
        'dateTo' => 'epoch',
        'recurrenceEndDate' => 'epoch?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_date' => array(
          'columns' => array('dateFrom', 'dateTo'),
        ),
        'key_instance' => array(
          'columns' => array('instanceOfEventPHID', 'sequenceIndex'),
          'unique' => true,
        ),
        'key_epoch' => array(
          'columns' => array('utcInitialEpoch', 'utcUntilEpoch'),
        ),
        'key_rdate' => array(
          'columns' => array('instanceOfEventPHID', 'utcInstanceEpoch'),
          'unique' => true,
        ),
      ),
      self::CONFIG_SERIALIZATION => array(
        'recurrenceFrequency' => self::SERIALIZATION_JSON,
        'parameters' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorCalendarEventPHIDType::TYPECONST);
  }

  public function getMonogram() {
    return 'E'.$this->getID();
  }

  public function getInvitees() {
    return $this->assertAttached($this->invitees);
  }

  public function attachInvitees(array $invitees) {
    $this->invitees = $invitees;
    return $this;
  }

  public function getInviteePHIDsForEdit() {
    $invitees = array();

    foreach ($this->getInvitees() as $invitee) {
      if ($invitee->isUninvited()) {
        continue;
      }
      $invitees[] = $invitee->getInviteePHID();
    }

    return $invitees;
  }

  public function getUserInviteStatus($phid) {
    $invitees = $this->getInvitees();
    $invitees = mpull($invitees, null, 'getInviteePHID');

    $invited = idx($invitees, $phid);
    if (!$invited) {
      return PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
    }
    $invited = $invited->getStatus();
    return $invited;
  }

  public function getIsUserAttending($phid) {
    $attending_status = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;

    $old_status = $this->getUserInviteStatus($phid);
    $is_attending = ($old_status == $attending_status);

    return $is_attending;
  }

  public function getIsUserInvited($phid) {
    $uninvited_status = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
    $declined_status = PhabricatorCalendarEventInvitee::STATUS_DECLINED;
    $status = $this->getUserInviteStatus($phid);
    if ($status == $uninvited_status || $status == $declined_status) {
      return false;
    }
    return true;
  }

  public function getIsGhostEvent() {
    return $this->isGhostEvent;
  }

  public function setIsGhostEvent($is_ghost_event) {
    $this->isGhostEvent = $is_ghost_event;
    return $this;
  }

  public function getURI() {
    if ($this->getIsGhostEvent()) {
      $base = $this->getParentEvent()->getURI();
      $sequence = $this->getSequenceIndex();
      return "{$base}/{$sequence}/";
    }

    return '/'.$this->getMonogram();
  }

  public function getParentEvent() {
    return $this->assertAttached($this->parentEvent);
  }

  public function attachParentEvent($event) {
    $this->parentEvent = $event;
    return $this;
  }

  public function isParentEvent() {
    return ($this->getIsRecurring() && !$this->getInstanceOfEventPHID());
  }

  public function isChildEvent() {
    return ($this->instanceOfEventPHID !== null);
  }

  public function isCancelledEvent() {
    if ($this->getIsCancelled()) {
      return true;
    }

    if ($this->isChildEvent()) {
      if ($this->getParentEvent()->getIsCancelled()) {
        return true;
      }
    }

    return false;
  }

  public function renderEventDate(
    PhabricatorUser $viewer,
    $show_end) {

    $start = $this->newStartDateTime();
    $end = $this->newEndDateTime();

    if ($show_end) {
      $min_date = $start->newPHPDateTime();
      $max_date = $end->newPHPDateTime();

      $min_day = $min_date->format('Y m d');
      $max_day = $max_date->format('Y m d');

      $show_end_date = ($min_day != $max_day);
    } else {
      $show_end_date = false;
    }

    $min_epoch = $start->getEpoch();
    $max_epoch = $end->getEpoch();

    if ($this->getIsAllDay()) {
      if ($show_end_date) {
        return pht(
          '%s - %s, All Day',
          phabricator_date($min_epoch, $viewer),
          phabricator_date($max_epoch, $viewer));
      } else {
        return pht(
          '%s, All Day',
          phabricator_date($min_epoch, $viewer));
      }
    } else if ($show_end_date) {
      return pht(
        '%s - %s',
        phabricator_datetime($min_epoch, $viewer),
        phabricator_datetime($max_epoch, $viewer));
    } else if ($show_end) {
      return pht(
        '%s - %s',
        phabricator_datetime($min_epoch, $viewer),
        phabricator_time($max_epoch, $viewer));
    } else {
      return pht(
        '%s',
        phabricator_datetime($min_epoch, $viewer));
    }
  }


  public function getDisplayIcon(PhabricatorUser $viewer) {
    if ($this->isCancelledEvent()) {
      return 'fa-times';
    }

    if ($viewer->isLoggedIn()) {
      $status = $this->getUserInviteStatus($viewer->getPHID());
      switch ($status) {
        case PhabricatorCalendarEventInvitee::STATUS_ATTENDING:
          return 'fa-check-circle';
        case PhabricatorCalendarEventInvitee::STATUS_INVITED:
          return 'fa-user-plus';
        case PhabricatorCalendarEventInvitee::STATUS_DECLINED:
          return 'fa-times';
      }
    }

    return $this->getIcon();
  }

  public function getDisplayIconColor(PhabricatorUser $viewer) {
    if ($this->isCancelledEvent()) {
      return 'red';
    }

    if ($viewer->isLoggedIn()) {
      $status = $this->getUserInviteStatus($viewer->getPHID());
      switch ($status) {
        case PhabricatorCalendarEventInvitee::STATUS_ATTENDING:
          return 'green';
        case PhabricatorCalendarEventInvitee::STATUS_INVITED:
          return 'green';
        case PhabricatorCalendarEventInvitee::STATUS_DECLINED:
          return 'grey';
      }
    }

    return 'bluegrey';
  }

  public function getDisplayIconLabel(PhabricatorUser $viewer) {
    if ($this->isCancelledEvent()) {
      return pht('Cancelled');
    }

    if ($viewer->isLoggedIn()) {
      $status = $this->getUserInviteStatus($viewer->getPHID());
      switch ($status) {
        case PhabricatorCalendarEventInvitee::STATUS_ATTENDING:
          return pht('Attending');
        case PhabricatorCalendarEventInvitee::STATUS_INVITED:
          return pht('Invited');
        case PhabricatorCalendarEventInvitee::STATUS_DECLINED:
          return pht('Declined');
      }
    }

    return null;
  }

  public function getICSFilename() {
    return $this->getMonogram().'.ics';
  }

  public function newIntermediateEventNode(
    PhabricatorUser $viewer,
    array $children) {

    $base_uri = new PhutilURI(PhabricatorEnv::getProductionURI('/'));
    $domain = $base_uri->getDomain();

    // NOTE: For recurring events, all of the events in the series have the
    // same UID (the UID of the parent). The child event instances are
    // differentiated by the "RECURRENCE-ID" field.
    if ($this->isChildEvent()) {
      $parent = $this->getParentEvent();
      $instance_datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch(
        $this->getUTCInstanceEpoch());
      $recurrence_id = $instance_datetime->getISO8601();
      $rrule = null;
    } else {
      $parent = $this;
      $recurrence_id = null;
      $rrule = $this->newRecurrenceRule();
    }
    $uid = $parent->getPHID().'@'.$domain;

    $created = $this->getDateCreated();
    $created = PhutilCalendarAbsoluteDateTime::newFromEpoch($created);

    $modified = $this->getDateModified();
    $modified = PhutilCalendarAbsoluteDateTime::newFromEpoch($modified);

    $date_start = $this->newStartDateTime();
    $date_end = $this->newEndDateTime();

    if ($this->getIsAllDay()) {
      $date_start->setIsAllDay(true);
      $date_end->setIsAllDay(true);
    }

    $host_phid = $this->getHostPHID();

    $invitees = $this->getInvitees();
    foreach ($invitees as $key => $invitee) {
      if ($invitee->isUninvited()) {
        unset($invitees[$key]);
      }
    }

    $phids = array();
    $phids[] = $host_phid;
    foreach ($invitees as $invitee) {
      $phids[] = $invitee->getInviteePHID();
    }

    $handles = $viewer->loadHandles($phids);

    $host_handle = $handles[$host_phid];
    $host_name = $host_handle->getFullName();
    $host_uri = $host_handle->getURI();
    $host_uri = PhabricatorEnv::getURI($host_uri);

    $organizer = id(new PhutilCalendarUserNode())
      ->setName($host_name)
      ->setURI($host_uri);

    $attendees = array();
    foreach ($invitees as $invitee) {
      $invitee_phid = $invitee->getInviteePHID();
      $invitee_handle = $handles[$invitee_phid];
      $invitee_name = $invitee_handle->getFullName();
      $invitee_uri = $invitee_handle->getURI();
      $invitee_uri = PhabricatorEnv::getURI($invitee_uri);

      switch ($invitee->getStatus()) {
        case PhabricatorCalendarEventInvitee::STATUS_ATTENDING:
          $status = PhutilCalendarUserNode::STATUS_ACCEPTED;
          break;
        case PhabricatorCalendarEventInvitee::STATUS_DECLINED:
          $status = PhutilCalendarUserNode::STATUS_DECLINED;
          break;
        case PhabricatorCalendarEventInvitee::STATUS_INVITED:
        default:
          $status = PhutilCalendarUserNode::STATUS_INVITED;
          break;
      }

      $attendees[] = id(new PhutilCalendarUserNode())
        ->setName($invitee_name)
        ->setURI($invitee_uri)
        ->setStatus($status);
    }

    // TODO: Use $children to generate EXDATE/RDATE information.

    $node = id(new PhutilCalendarEventNode())
      ->setUID($uid)
      ->setName($this->getName())
      ->setDescription($this->getDescription())
      ->setCreatedDateTime($created)
      ->setModifiedDateTime($modified)
      ->setStartDateTime($date_start)
      ->setEndDateTime($date_end)
      ->setOrganizer($organizer)
      ->setAttendees($attendees);

    if ($rrule) {
      $node->setRecurrenceRule($rrule);
    }

    if ($recurrence_id) {
      $node->setRecurrenceID($recurrence_id);
    }

    return $node;
  }

  public function newStartDateTime() {
    $datetime = $this->getParameter('startDateTime');
    if ($datetime) {
      return $this->newDateTimeFromDictionary($datetime);
    }

    $epoch = $this->getDateFrom();
    return $this->newDateTimeFromEpoch($epoch);
  }

  public function getStartDateTimeEpoch() {
    return $this->newStartDateTime()->getEpoch();
  }

  public function newEndDateTime() {
    $datetime = $this->getParameter('endDateTime');
    if ($datetime) {
      return $this->newDateTimeFromDictionary($datetime);
    }

    $epoch = $this->getDateTo();
    return $this->newDateTimeFromEpoch($epoch);
  }

  public function getEndDateTimeEpoch() {
    return $this->newEndDateTime()->getEpoch();
  }

  public function newUntilDateTime() {
    $datetime = $this->getParameter('untilDateTime');
    if ($datetime) {
      return $this->newDateTimeFromDictionary($datetime);
    }

    $epoch = $this->getRecurrenceEndDate();
    if (!$epoch) {
      return null;
    }
    return $this->newDateTimeFromEpoch($epoch);
  }

  public function getUntilDateTimeEpoch() {
    $datetime = $this->newUntilDateTime();

    if (!$datetime) {
      return null;
    }

    return $datetime->getEpoch();
  }

  public function newDuration() {
    return id(new PhutilCalendarDuration())
      ->setSeconds($this->getDuration());
  }

  public function newInstanceDateTime() {
    if (!$this->getIsRecurring()) {
      return null;
    }

    $index = $this->getSequenceIndex();
    if (!$index) {
      return null;
    }

    return $this->newSequenceIndexDateTime($index);
  }

  private function newDateTimeFromEpoch($epoch) {
    $datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch($epoch);

    if ($this->getIsAllDay()) {
      $datetime->setIsAllDay(true);
    }

    return $this->newDateTimeFromDateTime($datetime);
  }

  private function newDateTimeFromDictionary(array $dict) {
    $datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($dict);
    return $this->newDateTimeFromDateTime($datetime);
  }

  private function newDateTimeFromDateTime(PhutilCalendarDateTime $datetime) {
    $viewer_timezone = $this->viewerTimezone;
    if ($viewer_timezone) {
      $datetime->setViewerTimezone($viewer_timezone);
    }

    return $datetime;
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function setStartDateTime(PhutilCalendarDateTime $datetime) {
    return $this->setParameter(
      'startDateTime',
      $datetime->newAbsoluteDateTime()->toDictionary());
  }

  public function setEndDateTime(PhutilCalendarDateTime $datetime) {
    return $this->setParameter(
      'endDateTime',
      $datetime->newAbsoluteDateTime()->toDictionary());
  }

  public function setUntilDateTime(PhutilCalendarDateTime $datetime) {
    return $this->setParameter(
      'untilDateTime',
      $datetime->newAbsoluteDateTime()->toDictionary());
  }

  public function setRecurrenceRule(PhutilCalendarRecurrenceRule $rrule) {
    return $this->setParameter(
      'recurrenceRule',
      $rrule->toDictionary());
  }

  public function newRecurrenceRule() {
    if ($this->isChildEvent()) {
      return $this->getParentEvent()->newRecurrenceRule();
    }

    if (!$this->getIsRecurring()) {
      return null;
    }

    $dict = $this->getParameter('recurrenceRule');
    if (!$dict) {
      return null;
    }

    $rrule = PhutilCalendarRecurrenceRule::newFromDictionary($dict);

    $start = $this->newStartDateTime();
    $rrule->setStartDateTime($start);

    $until = $this->newUntilDateTime();
    if ($until) {
      $rrule->setUntil($until);
    }

    return $rrule;
  }

  public function newRecurrenceSet() {
    if ($this->isChildEvent()) {
      return $this->getParentEvent()->newRecurrenceSet();
    }

    $set = new PhutilCalendarRecurrenceSet();

    $rrule = $this->newRecurrenceRule();
    if (!$rrule) {
      return null;
    }

    $set->addSource($rrule);

    return $set;
  }

/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    $id = $this->getID();
    return "calendar:T{$id}:{$field}:{$hash}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getDescription();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newCalendarMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    // The host of an event can always view and edit it.
    $user_phid = $this->getHostPHID();
    if ($user_phid) {
      $viewer_phid = $viewer->getPHID();
      if ($viewer_phid == $user_phid) {
        return true;
      }
    }

    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      $status = $this->getUserInviteStatus($viewer->getPHID());
      if ($status == PhabricatorCalendarEventInvitee::STATUS_INVITED ||
        $status == PhabricatorCalendarEventInvitee::STATUS_ATTENDING ||
        $status == PhabricatorCalendarEventInvitee::STATUS_DECLINED) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The host of an event can always view and edit it. Users who are '.
      'invited to an event can always view it.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorCalendarEventEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorCalendarEventTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getHostPHID());
  }

/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getHostPHID());
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorCalendarEventFulltextEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the event.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('description')
        ->setType('string')
        ->setDescription(pht('The event description.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'description' => $this->getDescription(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
