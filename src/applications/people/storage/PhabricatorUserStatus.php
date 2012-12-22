<?php

final class PhabricatorUserStatus extends PhabricatorUserDAO {

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

  public function getTerseSummary(PhabricatorUser $viewer) {
    $until = phabricator_date($this->dateTo, $viewer);
    if ($this->status == PhabricatorUserStatus::STATUS_SPORADIC) {
      return 'Sporadic until '.$until;
    } else {
      return 'Away until '.$until;
    }
  }

  public function setTextStatus($status) {
    $statuses = array_flip(self::$statusTexts);
    return $this->setStatus($statuses[$status]);
  }

  public function loadCurrentStatuses($user_phids) {
    $statuses = $this->loadAllWhere(
      'userPHID IN (%Ls) AND UNIX_TIMESTAMP() BETWEEN dateFrom AND dateTo',
      $user_phids);
    return mpull($statuses, null, 'getUserPHID');
  }

  /**
   * Validates data and throws exceptions for non-sensical status
   * windows and attempts to create an overlapping status.
   */
  public function save() {

    if ($this->getDateTo() <= $this->getDateFrom()) {
      throw new PhabricatorUserStatusInvalidEpochException();
    }

    $this->openTransaction();
    $this->beginWriteLocking();

    if ($this->shouldInsertWhenSaved()) {

      $overlap = $this->loadAllWhere(
        'userPHID = %s AND dateFrom < %d AND dateTo > %d',
        $this->getUserPHID(),
        $this->getDateTo(),
        $this->getDateFrom());

      if ($overlap) {
        $this->endWriteLocking();
        $this->killTransaction();
        throw new PhabricatorUserStatusOverlapException();
      }
    }

    parent::save();

    $this->endWriteLocking();
    return $this->saveTransaction();
  }

}
