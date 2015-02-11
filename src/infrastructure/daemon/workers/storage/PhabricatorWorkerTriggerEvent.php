<?php

final class PhabricatorWorkerTriggerEvent
  extends PhabricatorWorkerDAO {

  protected $triggerID;
  protected $lastEventEpoch;
  protected $nextEventEpoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'lastEventEpoch' => 'epoch?',
        'nextEventEpoch' => 'epoch?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_trigger' => array(
          'columns' => array('triggerID'),
          'unique' => true,
        ),
        'key_next' => array(
          'columns' => array('nextEventEpoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewEvent(PhabricatorWorkerTrigger $trigger) {
    $event = new PhabricatorWorkerTriggerEvent();
    $event->setTriggerID($trigger->getID());
    return $event;
  }

}
