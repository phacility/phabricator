<?php

final class ConpherenceParticipant extends ConpherenceDAO {

  protected $participantPHID;
  protected $conpherencePHID;
  protected $seenMessageCount;
  protected $dateTouched;
  protected $settings = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'settings' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'dateTouched' => 'epoch',
        'seenMessageCount' => 'uint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'conpherencePHID' => array(
          'columns' => array('conpherencePHID', 'participantPHID'),
          'unique' => true,
        ),
        'participationIndex' => array(
          'columns' => array('participantPHID', 'dateTouched', 'id'),
        ),
        'key_thread' => array(
          'columns' => array('participantPHID', 'conpherencePHID'),
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
      $this->setSeenMessageCount($conpherence->getMessageCount());
      $this->save();

      PhabricatorUserCache::clearCache(
        PhabricatorUserMessageCountCacheType::KEY_COUNT,
        $this->getParticipantPHID());
    }

    return $this;
  }

  public function isUpToDate(ConpherenceThread $conpherence) {
    return ($this->getSeenMessageCount() == $conpherence->getMessageCount());
  }

}
