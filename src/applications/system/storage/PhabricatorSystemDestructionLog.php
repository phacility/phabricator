<?php

final class PhabricatorSystemDestructionLog extends PhabricatorSystemDAO {

  protected $objectClass;
  protected $rootLogID;
  protected $objectPHID;
  protected $objectMonogram;
  protected $epoch;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
