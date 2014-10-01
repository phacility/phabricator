<?php

final class PhabricatorCalendarEvent
  extends PhabricatorCalendarDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $dateFrom;
  protected $dateTo;
  protected $status;
  protected $description;

  const STATUS_AWAY = 1;
  const STATUS_SPORADIC = 2;

  private static $statusTexts = array(
    self::STATUS_AWAY => 'away',
    self::STATUS_SPORADIC => 'sporadic',
  );

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

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
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

  public function getTerseSummary(PhabricatorUser $viewer) {
    $until = phabricator_date($this->dateTo, $viewer);
    if ($this->status == PhabricatorCalendarEvent::STATUS_SPORADIC) {
      return pht('Sporadic until %s', $until);
    } else {
      return pht('Away until %s', $until);
    }
  }

  public function setTextStatus($status) {
    $statuses = array_flip(self::$statusTexts);
    return $this->setStatus($statuses[$status]);
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

}
