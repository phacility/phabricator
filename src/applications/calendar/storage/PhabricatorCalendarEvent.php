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
  protected $dateFrom;
  protected $dateTo;
  protected $allDayDateFrom;
  protected $allDayDateTo;
  protected $description;
  protected $isCancelled;
  protected $isAllDay;
  protected $icon;
  protected $mailKey;
  protected $isStub;

  protected $isRecurring = 0;
  protected $recurrenceFrequency = array();
  protected $recurrenceEndDate;

  private $isGhostEvent = false;
  protected $instanceOfEventPHID;
  protected $sequenceIndex;

  protected $viewPolicy;
  protected $editPolicy;

  protected $spacePHID;

  private $parentEvent = self::ATTACHABLE;
  private $invitees = self::ATTACHABLE;

  private $viewerDateFrom;
  private $viewerDateTo;

  // Frequency Constants
  const FREQUENCY_DAILY = 'daily';
  const FREQUENCY_WEEKLY = 'weekly';
  const FREQUENCY_MONTHLY = 'monthly';
  const FREQUENCY_YEARLY = 'yearly';

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

    $start = new DateTime('@'.$now);
    $start->setTimeZone($actor->getTimeZone());

    $start->setTime($start->format('H'), 0, 0);
    $start->modify('+1 hour');
    $end = id(clone $start)->modify('+1 hour');

    $epoch_min = $start->format('U');
    $epoch_max = $end->format('U');

    $now_date = new DateTime('@'.$now);
    $now_min = id(clone $now_date)->setTime(0, 0)->format('U');
    $now_max = id(clone $now_date)->setTime(23, 59)->format('U');

    $default_icon = 'fa-calendar';

    return id(new PhabricatorCalendarEvent())
      ->setHostPHID($actor->getPHID())
      ->setIsCancelled(0)
      ->setIsAllDay(0)
      ->setIsStub(0)
      ->setIsRecurring(0)
      ->setRecurrenceFrequency(
        array(
          'rule' => self::FREQUENCY_WEEKLY,
        ))
      ->setIcon($default_icon)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID())
      ->attachInvitees(array())
      ->setDateFrom($epoch_min)
      ->setDateTo($epoch_max)
      ->setAllDayDateFrom($now_min)
      ->setAllDayDateTo($now_max)
      ->applyViewerTimezone($actor);
  }

  private function newChild(PhabricatorUser $actor, $sequence) {
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
      ->setRecurrenceFrequency($this->getRecurrenceFrequency())
      ->attachParentEvent($this);

    return $child->copyFromParent($actor);
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
    // example, we want any changes to the parent event's name to
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


  public function copyFromParent(PhabricatorUser $actor) {
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

    $frequency = $parent->getFrequencyUnit();
    $modify_key = '+'.$this->getSequenceIndex().' '.$frequency;

    $date = $parent->getDateFrom();
    $date_time = PhabricatorTime::getDateTimeFromEpoch($date, $actor);
    $date_time->modify($modify_key);
    $date = $date_time->format('U');

    $duration = $this->getDuration();

    $utc = new DateTimeZone('UTC');

    $allday_from = $parent->getAllDayDateFrom();
    $allday_date = new DateTime('@'.$allday_from, $utc);
    $allday_date->setTimeZone($utc);
    $allday_date->modify($modify_key);

    $allday_min = $allday_date->format('U');
    $allday_duration = ($parent->getAllDayDateTo() - $allday_from);

    $this
      ->setDateFrom($date)
      ->setDateTo($date + $duration)
      ->setAllDayDateFrom($allday_min)
      ->setAllDayDateTo($allday_min + $allday_duration);

    return $this;
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

  public function newGhost(PhabricatorUser $actor, $sequence) {
    $ghost = $this->newChild($actor, $sequence);

    $ghost
      ->setIsGhostEvent(true)
      ->makeEphemeral();

    $ghost->applyViewerTimezone($actor);

    return $ghost;
  }

  public function getViewerDateFrom() {
    if ($this->viewerDateFrom === null) {
      throw new PhutilInvalidStateException('applyViewerTimezone');
    }

    return $this->viewerDateFrom;
  }

  public function getViewerDateTo() {
    if ($this->viewerDateTo === null) {
      throw new PhutilInvalidStateException('applyViewerTimezone');
    }

    return $this->viewerDateTo;
  }

  public function applyViewerTimezone(PhabricatorUser $viewer) {
    if (!$this->getIsAllDay()) {
      $this->viewerDateFrom = $this->getDateFrom();
      $this->viewerDateTo = $this->getDateTo();
    } else {
      $zone = $viewer->getTimeZone();

      $this->viewerDateFrom = $this->getDateEpochForTimezone(
        $this->getAllDayDateFrom(),
        new DateTimeZone('UTC'),
        'Y-m-d',
        null,
        $zone);

      $this->viewerDateTo = $this->getDateEpochForTimezone(
        $this->getAllDayDateTo(),
        new DateTimeZone('UTC'),
        'Y-m-d 23:59:00',
        null,
        $zone);
    }

    return $this;
  }

  public function getDuration() {
    return $this->getDateTo() - $this->getDateFrom();
  }

  public function getDateEpochForTimezone(
    $epoch,
    $src_zone,
    $format,
    $adjust,
    $dst_zone) {

    $src = new DateTime('@'.$epoch);
    $src->setTimeZone($src_zone);

    if (strlen($adjust)) {
      $adjust = ' '.$adjust;
    }

    $dst = new DateTime($src->format($format).$adjust, $dst_zone);
    return $dst->format('U');
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

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
  public function getDateFromForCache() {
    return ($this->getViewerDateFrom() - phutil_units('15 minutes in seconds'));
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'dateFrom' => 'epoch',
        'dateTo' => 'epoch',
        'allDayDateFrom' => 'epoch',
        'allDayDateTo' => 'epoch',
        'description' => 'text',
        'isCancelled' => 'bool',
        'isAllDay' => 'bool',
        'icon' => 'text32',
        'mailKey' => 'bytes20',
        'isRecurring' => 'bool',
        'recurrenceEndDate' => 'epoch?',
        'instanceOfEventPHID' => 'phid?',
        'sequenceIndex' => 'uint32?',
        'isStub' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_date' => array(
          'columns' => array('dateFrom', 'dateTo'),
        ),
        'key_instance' => array(
          'columns' => array('instanceOfEventPHID', 'sequenceIndex'),
          'unique' => true,
        ),
      ),
      self::CONFIG_SERIALIZATION => array(
        'recurrenceFrequency' => self::SERIALIZATION_JSON,
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

  public function getFrequencyRule() {
    return idx($this->recurrenceFrequency, 'rule');
  }

  public function getFrequencyUnit() {
    $frequency = $this->getFrequencyRule();

    switch ($frequency) {
      case 'daily':
        return 'day';
      case 'weekly':
        return 'week';
      case 'monthly':
        return 'month';
      case 'yearly':
        return 'year';
      default:
        return 'day';
    }
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

    if ($show_end) {
      $min_date = PhabricatorTime::getDateTimeFromEpoch(
        $this->getViewerDateFrom(),
        $viewer);

      $max_date = PhabricatorTime::getDateTimeFromEpoch(
        $this->getViewerDateTo(),
        $viewer);

      $min_day = $min_date->format('Y m d');
      $max_day = $max_date->format('Y m d');

      $show_end_date = ($min_day != $max_day);
    } else {
      $show_end_date = false;
    }

    $min_epoch = $this->getViewerDateFrom();
    $max_epoch = $this->getViewerDateTo();

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
