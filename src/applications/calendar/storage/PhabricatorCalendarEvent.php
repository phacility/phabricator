<?php

final class PhabricatorCalendarEvent extends PhabricatorCalendarDAO
  implements PhabricatorPolicyInterface,
  PhabricatorMarkupInterface,
  PhabricatorApplicationTransactionInterface,
  PhabricatorSubscribableInterface,
  PhabricatorTokenReceiverInterface,
  PhabricatorMentionableInterface,
  PhabricatorFlaggableInterface {

  protected $name;
  protected $userPHID;
  protected $dateFrom;
  protected $dateTo;
  protected $status;
  protected $description;

  const STATUS_AWAY = 1;
  const STATUS_SPORADIC = 2;

  public static function initializeNewCalendarEvent(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorCalendarApplication'))
      ->executeOne();

    return id(new PhabricatorCalendarEvent())
      ->setUserPHID($actor->getPHID());
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

  public function getHumanStatus() {
    $options = $this->getStatusOptions();
    return $options[$this->status];
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

  /**
   * Validates data and throws exceptions for non-sensical status
   * windows
   */
  public function save() {

    if ($this->getDateTo() <= $this->getDateFrom()) {
      throw new PhabricatorCalendarEventInvalidEpochException();
    }

    return parent::save();
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
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getUserPHID();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
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
}
