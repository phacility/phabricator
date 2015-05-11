<?php

final class PhabricatorCalendarEvent extends PhabricatorCalendarDAO
  implements PhabricatorPolicyInterface,
  PhabricatorMarkupInterface,
  PhabricatorApplicationTransactionInterface,
  PhabricatorSubscribableInterface,
  PhabricatorTokenReceiverInterface,
  PhabricatorDestructibleInterface,
  PhabricatorMentionableInterface,
  PhabricatorFlaggableInterface {

  protected $name;
  protected $userPHID;
  protected $dateFrom;
  protected $dateTo;
  protected $status;
  protected $description;
  protected $isCancelled;
  protected $isAllDay;
  protected $mailKey;

  protected $viewPolicy;
  protected $editPolicy;

  private $invitees = self::ATTACHABLE;
  private $appliedViewer;

  const STATUS_AWAY = 1;
  const STATUS_SPORADIC = 2;

  public static function initializeNewCalendarEvent(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorCalendarApplication'))
      ->executeOne();

    return id(new PhabricatorCalendarEvent())
      ->setUserPHID($actor->getPHID())
      ->setIsCancelled(0)
      ->setIsAllDay(0)
      ->setViewPolicy($actor->getPHID())
      ->setEditPolicy($actor->getPHID())
      ->attachInvitees(array())
      ->applyViewerTimezone($actor);
  }

  public function applyViewerTimezone(PhabricatorUser $viewer) {
    if ($this->appliedViewer) {
      throw new Exception(pht('Viewer timezone is already applied!'));
    }

    $this->appliedViewer = $viewer;

    if (!$this->getIsAllDay()) {
      return $this;
    }

    $zone = $viewer->getTimeZone();


    $this->setDateFrom(
      $this->getDateEpochForTimeZone(
        $this->getDateFrom(),
        new DateTimeZone('Pacific/Kiritimati'),
        'Y-m-d',
        null,
        $zone));

    $this->setDateTo(
      $this->getDateEpochForTimeZone(
        $this->getDateTo(),
        new DateTimeZone('Pacific/Midway'),
        'Y-m-d 23:59:00',
        '-1 day',
        $zone));

    return $this;
  }


  public function removeViewerTimezone(PhabricatorUser $viewer) {
    if (!$this->appliedViewer) {
      throw new Exception(pht('Viewer timezone is not applied!'));
    }

    if ($viewer->getPHID() != $this->appliedViewer->getPHID()) {
      throw new Exception(pht('Removed viewer must match applied viewer!'));
    }

    $this->appliedViewer = null;

    if (!$this->getIsAllDay()) {
      return $this;
    }

    $zone = $viewer->getTimeZone();

    $this->setDateFrom(
      $this->getDateEpochForTimeZone(
        $this->getDateFrom(),
        $zone,
        'Y-m-d',
        null,
        new DateTimeZone('Pacific/Kiritimati')));

    $this->setDateTo(
      $this->getDateEpochForTimeZone(
        $this->getDateTo(),
        $zone,
        'Y-m-d',
        '+1 day',
        new DateTimeZone('Pacific/Midway')));

    return $this;
  }

  private function getDateEpochForTimeZone(
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
    if ($this->appliedViewer) {
      throw new Exception(
        pht(
          'Can not save event with viewer timezone still applied!'));
    }

    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    return parent::save();
  }

  private static $statusTexts = array(
    self::STATUS_AWAY => 'away',
    self::STATUS_SPORADIC => 'sporadic',
  );

  public function setTextStatus($status) {
    $statuses = array_flip(self::$statusTexts);
    return $this->setStatus($statuses[$status]);
  }

  public function getTextStatus() {
    return self::$statusTexts[$this->status];
  }

  public function getStatusOptions() {
    return array(
      self::STATUS_AWAY     => pht('Away'),
      self::STATUS_SPORADIC => pht('Sporadic'),
    );
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'dateFrom' => 'epoch',
        'dateTo' => 'epoch',
        'status' => 'uint32',
        'description' => 'text',
        'isCancelled' => 'bool',
        'isAllDay' => 'bool',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID_dateFrom' => array(
          'columns' => array('userPHID', 'dateTo'),
        ),
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

  public function getTerseSummary(PhabricatorUser $viewer) {
    $until = phabricator_date($this->dateTo, $viewer);
    if ($this->status == PhabricatorCalendarEvent::STATUS_SPORADIC) {
      return pht('Sporadic until %s', $until);
    } else {
      return pht('Away until %s', $until);
    }
  }

  public static function getNameForStatus($value) {
    switch ($value) {
      case self::STATUS_AWAY:
        return pht('Away');
      case self::STATUS_SPORADIC:
        return pht('Sporadic');
      default:
        return pht('Unknown');
    }
  }

  public function loadCurrentStatuses($user_phids) {
    if (!$user_phids) {
      return array();
    }

    $statuses = $this->loadAllWhere(
      'userPHID IN (%Ls) AND UNIX_TIMESTAMP() BETWEEN dateFrom AND dateTo',
      $user_phids);

    return mpull($statuses, null, 'getUserPHID');
  }

  public function getInvitees() {
    return $this->assertAttached($this->invitees);
  }

  public function attachInvitees(array $invitees) {
    $this->invitees = $invitees;
    return $this;
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
    // The owner of a task can always view and edit it.
    $user_phid = $this->getUserPHID();
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
    return pht('The owner of an event can always view and edit it,
      and invitees can always view it.');
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
    return ($phid == $this->getUserPHID());
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }

/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getUserPHID());
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }
}
