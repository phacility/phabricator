<?php

final class PhabricatorTimelineEventData extends PhabricatorTimelineDAO {

  protected $eventData;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'eventData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
