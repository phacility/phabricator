<?php

final class PhabricatorCalendarEvent extends PhabricatorCalendarDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorPolicyCodexInterface,
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
    PhabricatorFerretInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $hostPHID;
  protected $description;
  protected $isCancelled;
  protected $isAllDay;
  protected $icon;
  protected $isStub;

  protected $isRecurring = 0;

  protected $seriesParentPHID;
  protected $instanceOfEventPHID;
  protected $sequenceIndex;

  protected $viewPolicy;
  protected $editPolicy;

  protected $spacePHID;

  protected $utcInitialEpoch;
  protected $utcUntilEpoch;
  protected $utcInstanceEpoch;
  protected $parameters = array();

  protected $importAuthorPHID;
  protected $importSourcePHID;
  protected $importUIDIndex;
  protected $importUID;

  private $parentEvent = self::ATTACHABLE;
  private $invitees = self::ATTACHABLE;
  private $importSource = self::ATTACHABLE;
  private $rsvps = self::ATTACHABLE;

  private $viewerTimezone;

  private $isGhostEvent = false;
  private $stubInvitees;

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

    $datetime_defaults = self::newDefaultEventDateTimes(
      $actor,
      $now);
    list($datetime_start, $datetime_end) = $datetime_defaults;

    // When importing events from a context like "bin/calendar reload", we may
    // be acting as the omnipotent user.
    $host_phid = $actor->getPHID();
    if (!$host_phid) {
      $host_phid = $app->getPHID();
    }

    return id(new PhabricatorCalendarEvent())
      ->setDescription('')
      ->setHostPHID($host_phid)
      ->setIsCancelled(0)
      ->setIsAllDay(0)
      ->setIsStub(0)
      ->setIsRecurring(0)
      ->setIcon($default_icon)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID())
      ->attachInvitees(array())
      ->setStartDateTime($datetime_start)
      ->setEndDateTime($datetime_end)
      ->attachImportSource(null)
      ->applyViewerTimezone($actor);
  }

  public static function newDefaultEventDateTimes(
    PhabricatorUser $viewer,
    $now) {

    $datetime_start = PhutilCalendarAbsoluteDateTime::newFromEpoch(
      $now,
      $viewer->getTimezoneIdentifier());

    // Advance the time by an hour, then round downwards to the nearest hour.
    // For example, if it is currently 3:25 PM, we suggest a default start time
    // of 4 PM.
    $datetime_start = $datetime_start
      ->newRelativeDateTime('PT1H')
      ->newAbsoluteDateTime();
    $datetime_start->setMinute(0);
    $datetime_start->setSecond(0);

    // Default the end time to an hour after the start time.
    $datetime_end = $datetime_start
      ->newRelativeDateTime('PT1H')
      ->newAbsoluteDateTime();

    return array($datetime_start, $datetime_end);
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

    $series_phid = $this->getSeriesParentPHID();
    if (!$series_phid) {
      $series_phid = $this->getPHID();
    }

    $child = id(new self())
      ->setIsCancelled(0)
      ->setIsStub(0)
      ->setInstanceOfEventPHID($this->getPHID())
      ->setSeriesParentPHID($series_phid)
      ->setSequenceIndex($sequence)
      ->setIsRecurring(true)
      ->attachParentEvent($this)
      ->attachImportSource(null);

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
      'isCancelled' => true,
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
      ->setDescription($parent->getDescription())
      ->setIsCancelled($parent->getIsCancelled());

    if ($start) {
      $start_datetime = $start;
    } else {
      $sequence = $this->getSequenceIndex();
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

    if ($parent->isImportedEvent()) {
      $full_uid = $parent->getImportUID().'/'.$start_datetime->getEpoch();

      // NOTE: We don't attach the import source because this gets called
      // from CalendarEventQuery while building ghosts, before we've loaded
      // and attached sources. Possibly this sequence should be flipped.

      $this
        ->setImportAuthorPHID($parent->getImportAuthorPHID())
        ->setImportSourcePHID($parent->getImportSourcePHID())
        ->setImportUID($full_uid);
    }

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

    $limit = $sequence + 1;
    $count = $this->getRecurrenceCount();
    if ($count && ($count < $limit)) {
      return null;
    }

    $instances = $set->getEventsBetween(
      null,
      $this->newUntilDateTime(),
      $limit);

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
    $import_uid = $this->getImportUID();
    if ($import_uid !== null) {
      $index = PhabricatorHash::digestForIndex($import_uid);
    } else {
      $index = null;
    }
    $this->setImportUIDIndex($index);

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

  public function getEndDateTimeEpochForCache() {
    return $this->getEndDateTimeEpoch();
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
        'isRecurring' => 'bool',
        'seriesParentPHID' => 'phid?',
        'instanceOfEventPHID' => 'phid?',
        'sequenceIndex' => 'uint32?',
        'isStub' => 'bool',
        'utcInitialEpoch' => 'epoch',
        'utcUntilEpoch' => 'epoch?',
        'utcInstanceEpoch' => 'epoch?',

        'importAuthorPHID' => 'phid?',
        'importSourcePHID' => 'phid?',
        'importUIDIndex' => 'bytes12?',
        'importUID' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
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
        'key_series' => array(
          'columns' => array('seriesParentPHID', 'utcInitialEpoch'),
        ),
      ),
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorCalendarEventPHIDType::TYPECONST;
  }

  public function getMonogram() {
    return 'E'.$this->getID();
  }

  public function getInvitees() {
    if ($this->getIsGhostEvent() || $this->getIsStub()) {
      if ($this->stubInvitees === null) {
        $this->stubInvitees = $this->newStubInvitees();
      }
      return $this->stubInvitees;
    }

    return $this->assertAttached($this->invitees);
  }

  public function getInviteeForPHID($phid) {
    $invitees = $this->getInvitees();
    $invitees = mpull($invitees, null, 'getInviteePHID');
    return idx($invitees, $phid);
  }

  public static function getFrequencyMap() {
    return array(
      PhutilCalendarRecurrenceRule::FREQUENCY_DAILY => array(
        'label' => pht('Daily'),
      ),
      PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY => array(
        'label' => pht('Weekly'),
      ),
      PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY => array(
        'label' => pht('Monthly'),
      ),
      PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY => array(
        'label' => pht('Yearly'),
      ),
    );
  }

  private function newStubInvitees() {
    $parent = $this->getParentEvent();

    $parent_invitees = $parent->getInvitees();
    $stub_invitees = array();

    foreach ($parent_invitees as $invitee) {
      $stub_invitee = id(new PhabricatorCalendarEventInvitee())
        ->setInviteePHID($invitee->getInviteePHID())
        ->setInviterPHID($invitee->getInviterPHID())
        ->setStatus(PhabricatorCalendarEventInvitee::STATUS_INVITED);

      $stub_invitees[] = $stub_invitee;
    }

    return $stub_invitees;
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

  public function attachParentEvent(PhabricatorCalendarEvent $event = null) {
    $this->parentEvent = $event;
    return $this;
  }

  public function isParentEvent() {
    return ($this->getIsRecurring() && !$this->getInstanceOfEventPHID());
  }

  public function isChildEvent() {
    return ($this->instanceOfEventPHID !== null);
  }

  public function renderEventDate(
    PhabricatorUser $viewer,
    $show_end) {

    $start = $this->newStartDateTime();
    $end = $this->newEndDateTime();

    $min_date = $start->newPHPDateTime();
    $max_date = $end->newPHPDateTime();

    if ($this->getIsAllDay()) {
      // Subtract one second since the stored date is exclusive.
      $max_date = $max_date->modify('-1 second');
    }

    if ($show_end) {
      $min_day = $min_date->format('Y m d');
      $max_day = $max_date->format('Y m d');

      $show_end_date = ($min_day != $max_day);
    } else {
      $show_end_date = false;
    }

    $min_epoch = $min_date->format('U');
    $max_epoch = $max_date->format('U');

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
    if ($this->getIsCancelled()) {
      return 'fa-times';
    }

    if ($viewer->isLoggedIn()) {
      $viewer_phid = $viewer->getPHID();
      if ($this->isRSVPInvited($viewer_phid)) {
        return 'fa-users';
      } else {
        $status = $this->getUserInviteStatus($viewer_phid);
        switch ($status) {
          case PhabricatorCalendarEventInvitee::STATUS_ATTENDING:
            return 'fa-check-circle';
          case PhabricatorCalendarEventInvitee::STATUS_INVITED:
            return 'fa-user-plus';
          case PhabricatorCalendarEventInvitee::STATUS_DECLINED:
            return 'fa-times-circle';
        }
      }
    }

    if ($this->isImportedEvent()) {
      return 'fa-download';
    }

    return $this->getIcon();
  }

  public function getDisplayIconColor(PhabricatorUser $viewer) {
    if ($this->getIsCancelled()) {
      return 'red';
    }

    if ($this->isImportedEvent()) {
      return 'orange';
    }

    if ($viewer->isLoggedIn()) {
      $viewer_phid = $viewer->getPHID();
      if ($this->isRSVPInvited($viewer_phid)) {
        return 'green';
      }

      $status = $this->getUserInviteStatus($viewer_phid);
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
    if ($this->getIsCancelled()) {
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

    // NOTE: Gmail shows "Who: Unknown Organizer*" if the organizer URI does
    // not look like an email address. Use a synthetic address so it shows
    // the host name instead.
    $install_uri = PhabricatorEnv::getProductionURI('/');
    $install_uri = new PhutilURI($install_uri);

    // This should possibly use "metamta.reply-handler-domain" instead, but
    // we do not currently accept mail for users anyway, and that option may
    // not be configured.
    $mail_domain = $install_uri->getDomain();
    $host_uri = "mailto:{$host_phid}@{$mail_domain}";

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
    return $this->newDateTimeFromDictionary($datetime);
  }

  public function getStartDateTimeEpoch() {
    return $this->newStartDateTime()->getEpoch();
  }

  public function newEndDateTimeForEdit() {
    $datetime = $this->getParameter('endDateTime');
    return $this->newDateTimeFromDictionary($datetime);
  }

  public function newEndDateTime() {
    $datetime = $this->newEndDateTimeForEdit();

    // If this is an all day event, we move the end date time forward to the
    // first second of the following day. This is consistent with what users
    // expect: an all day event from "Nov 1" to "Nov 1" lasts the entire day.

    // For imported events, the end date is already stored with this
    // adjustment.

    if ($this->getIsAllDay() && !$this->isImportedEvent()) {
      $datetime = $datetime
        ->newAbsoluteDateTime()
        ->setHour(0)
        ->setMinute(0)
        ->setSecond(0)
        ->newRelativeDateTime('P1D')
        ->newAbsoluteDateTime();
    }

    return $datetime;
  }

  public function getEndDateTimeEpoch() {
    return $this->newEndDateTime()->getEpoch();
  }

  public function newUntilDateTime() {
    $datetime = $this->getParameter('untilDateTime');
    if ($datetime) {
      return $this->newDateTimeFromDictionary($datetime);
    }

    return null;
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

  public function setUntilDateTime(PhutilCalendarDateTime $datetime = null) {
    if ($datetime) {
      $value = $datetime->newAbsoluteDateTime()->toDictionary();
    } else {
      $value = null;
    }

    return $this->setParameter('untilDateTime', $value);
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

    $count = $this->getRecurrenceCount();
    if ($count) {
      $rrule->setCount($count);
    }

    return $rrule;
  }

  public function getRecurrenceCount() {
    $count = (int)$this->getParameter('recurrenceCount');

    if (!$count) {
      return null;
    }

    return $count;
  }

  public function newRecurrenceSet() {
    if ($this->isChildEvent()) {
      return $this->getParentEvent()->newRecurrenceSet();
    }

    $set = new PhutilCalendarRecurrenceSet();

    if ($this->viewerTimezone) {
      $set->setViewerTimezone($this->viewerTimezone);
    }

    $rrule = $this->newRecurrenceRule();
    if (!$rrule) {
      return null;
    }

    $set->addSource($rrule);

    return $set;
  }

  public function isImportedEvent() {
    return (bool)$this->getImportSourcePHID();
  }

  public function getImportSource() {
    return $this->assertAttached($this->importSource);
  }

  public function attachImportSource(
    PhabricatorCalendarImport $import = null) {
    $this->importSource = $import;
    return $this;
  }

  public function loadForkTarget(PhabricatorUser $viewer) {
    if (!$this->getIsRecurring()) {
      // Can't fork an event which isn't recurring.
      return null;
    }

    if ($this->isChildEvent()) {
      // If this is a child event, this is the fork target.
      return $this;
    }

    if (!$this->isValidSequenceIndex($viewer, 1)) {
      // This appears to be a "recurring" event with no valid instances: for
      // example, its "until" date is before the second instance would occur.
      // This can happen if we already forked the event or if users entered
      // silly stuff. Just edit the event directly without forking anything.
      return null;
    }


    $next_event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withInstanceSequencePairs(
        array(
          array($this->getPHID(), 1),
        ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$next_event) {
      $next_event = $this->newStub($viewer, 1);
    }

    return $next_event;
  }

  public function loadFutureEvents(PhabricatorUser $viewer) {
    // NOTE: If you can't edit some of the future events, we just
    // don't try to update them. This seems like it's probably what
    // users are likely to expect.

    // NOTE: This only affects events that are currently in the same
    // series, not all events that were ever in the original series.
    // We could use series PHIDs instead of parent PHIDs to affect more
    // events if this turns out to be counterintuitive. Other
    // applications differ in their behavior.

    return id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withParentEventPHIDs(array($this->getPHID()))
      ->withUTCInitialEpochBetween($this->getUTCInitialEpoch(), null)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
  }

  public function getNotificationPHIDs() {
    $phids = array();
    if ($this->getPHID()) {
      $phids[] = $this->getPHID();
    }

    if ($this->getSeriesParentPHID()) {
      $phids[] = $this->getSeriesParentPHID();
    }

    return $phids;
  }

  public function getRSVPs($phid) {
    return $this->assertAttachedKey($this->rsvps, $phid);
  }

  public function attachRSVPs(array $rsvps) {
    $this->rsvps = $rsvps;
    return $this;
  }

  public function isRSVPInvited($phid) {
    $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    return ($this->getRSVPStatus($phid) == $status_invited);
  }

  public function hasRSVPAuthority($phid, $other_phid) {
    foreach ($this->getRSVPs($phid) as $rsvp) {
      if ($rsvp->getInviteePHID() == $other_phid) {
        return true;
      }
    }

    return false;
  }

  public function getRSVPStatus($phid) {
    // Check for an individual invitee record first.
    $invitees = $this->invitees;
    $invitees = mpull($invitees, null, 'getInviteePHID');
    $invitee = idx($invitees, $phid);
    if ($invitee) {
      return $invitee->getStatus();
    }

    // If we don't have one, try to find an invited status for the user's
    // projects.
    $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    foreach ($this->getRSVPs($phid) as $rsvp) {
      if ($rsvp->getStatus() == $status_invited) {
        return $status_invited;
      }
    }

    return PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
  }



/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    $content = $this->getMarkupText($field);
    return PhabricatorMarkupEngine::digestRemarkupContent($this, $content);
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
        if ($this->isImportedEvent()) {
          return PhabricatorPolicies::POLICY_NOONE;
        } else {
          return $this->getEditPolicy();
        }
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->isImportedEvent()) {
      return false;
    }

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


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $import_source = $this->getImportSource();
        if ($import_source) {
          $extended[] = array(
            $import_source,
            PhabricatorPolicyCapability::CAN_VIEW,
          );
        }
        break;
    }

    return $extended;
  }

/* -(  PhabricatorPolicyCodexInterface  )------------------------------------ */

  public function newPolicyCodex() {
    return new PhabricatorCalendarEventPolicyCodex();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorCalendarEventEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorCalendarEventTransaction();
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
      $invitees = id(new PhabricatorCalendarEventInvitee())->loadAllWhere(
        'eventPHID = %s',
        $this->getPHID());
      foreach ($invitees as $invitee) {
        $invitee->delete();
      }

      $notifications = id(new PhabricatorCalendarNotification())->loadAllWhere(
        'eventPHID = %s',
        $this->getPHID());
      foreach ($notifications as $notification) {
        $notification->delete();
      }

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


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PhabricatorCalendarEventFerretEngine();
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
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isAllDay')
        ->setType('bool')
        ->setDescription(pht('True if the event is an all day event.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('startDateTime')
        ->setType('datetime')
        ->setDescription(pht('Start date and time of the event.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('endDateTime')
        ->setType('datetime')
        ->setDescription(pht('End date and time of the event.')),
    );
  }

  public function getFieldValuesForConduit() {
    $start_datetime = $this->newStartDateTime();
    $end_datetime = $this->newEndDateTime();

    return array(
      'name' => $this->getName(),
      'description' => $this->getDescription(),
      'isAllDay' => (bool)$this->getIsAllDay(),
      'startDateTime' => $this->getConduitDateTime($start_datetime),
      'endDateTime' => $this->getConduitDateTime($end_datetime),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

  private function getConduitDateTime($datetime) {
    if (!$datetime) {
      return null;
    }

    $epoch = $datetime->getEpoch();

    // TODO: Possibly pass the actual viewer in from the Conduit stuff, or
    // retain it when setting the viewer timezone?
    $viewer = id(new PhabricatorUser())
      ->overrideTimezoneIdentifier($this->viewerTimezone);

    return array(
      'epoch' => (int)$epoch,
      'display' => array(
        'default' => phabricator_datetime($epoch, $viewer),
      ),
      'iso8601' => $datetime->getISO8601(),
      'timezone' => $this->viewerTimezone,
    );
  }

}
