<?php

final class ConpherenceParticipant extends ConpherenceDAO {

  protected $participantPHID;
  protected $conpherencePHID;
  protected $participationStatus;
  protected $behindTransactionPHID;
  protected $seenMessageCount;
  protected $dateTouched;
  protected $settings = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'settings' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'participationStatus' => 'uint32',
        'dateTouched' => 'epoch',
        'seenMessageCount' => 'uint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'conpherencePHID' => array(
          'columns' => array('conpherencePHID', 'participantPHID'),
          'unique' => true,
        ),
        'unreadCount' => array(
          'columns' => array('participantPHID', 'participationStatus'),
        ),
        'participationIndex' => array(
          'columns' => array('participantPHID', 'dateTouched', 'id'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getSettings() {
    return nonempty($this->settings, array());
  }

  public function markUpToDate(
    ConpherenceThread $conpherence,
    ConpherenceTransaction $xaction) {
    if (!$this->isUpToDate($conpherence)) {
      $this->setParticipationStatus(ConpherenceParticipationStatus::UP_TO_DATE);
      $this->setBehindTransactionPHID($xaction->getPHID());
      $this->setSeenMessageCount($conpherence->getMessageCount());
      $this->save();

      PhabricatorUserCache::clearCache(
        PhabricatorUserMessageCountCacheType::KEY_COUNT,
        $this->getParticipantPHID());
    }

    return $this;
  }

  public function isUpToDate(ConpherenceThread $conpherence) {
    return
      ($this->getSeenMessageCount() == $conpherence->getMessageCount())
        &&
      ($this->getParticipationStatus() ==
       ConpherenceParticipationStatus::UP_TO_DATE);
  }

}
