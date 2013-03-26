<?php

/**
 * @group conpherence
 */
final class ConpherenceParticipant extends ConpherenceDAO {

  protected $participantPHID;
  protected $conpherencePHID;
  protected $participationStatus;
  protected $behindTransactionPHID;
  protected $dateTouched;
  protected $settings = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'settings' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getSettings() {
    return nonempty($this->settings, array());
  }

  public function markUpToDate(ConpherenceTransaction $xaction) {
    if (!$this->isUpToDate()) {
      $this->setParticipationStatus(ConpherenceParticipationStatus::UP_TO_DATE);
      $this->setBehindTransactionPHID($xaction->getPHID());
      $this->save();
    }
    return $this;
  }

  public function isUpToDate() {
    return $this->getParticipationStatus() ==
           ConpherenceParticipationStatus::UP_TO_DATE;
  }

}
