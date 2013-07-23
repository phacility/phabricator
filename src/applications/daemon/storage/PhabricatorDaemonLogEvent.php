<?php

final class PhabricatorDaemonLogEvent extends PhabricatorDaemonDAO {

  protected $logID;
  protected $logType;
  protected $message;
  protected $epoch;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
