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

  public function markUpToDate() {
    if (!$this->isUpToDate()) {
      $this->setParticipationStatus(ConpherenceParticipationStatus::UP_TO_DATE);
      $this->save();
    }
    return $this;
  }

  public function isUpToDate() {
    return $this->getParticipationStatus() ==
           ConpherenceParticipationStatus::UP_TO_DATE;
  }

}
