<?php

final class PhabricatorTimelineCursor extends PhabricatorTimelineDAO {

  protected $name;
  protected $position;

  public function getIDKey() {
    return 'name';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function shouldInsertWhenSaved() {
    if ($this->position == 0) {
      return true;
    }
    return false;
  }

}
