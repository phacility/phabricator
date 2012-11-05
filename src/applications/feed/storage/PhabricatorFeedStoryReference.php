<?php

final class PhabricatorFeedStoryReference extends PhabricatorFeedDAO {

  protected $objectPHID;
  protected $chronologicalKey;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
    ) + parent::getConfiguration();
  }

}
