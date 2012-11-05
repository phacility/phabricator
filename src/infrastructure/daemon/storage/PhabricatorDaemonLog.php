<?php

final class PhabricatorDaemonLog extends PhabricatorDaemonDAO {

  const STATUS_UNKNOWN = 'unknown';
  const STATUS_RUNNING = 'run';
  const STATUS_DEAD    = 'dead';
  const STATUS_EXITED  = 'exit';

  protected $daemon;
  protected $host;
  protected $pid;
  protected $argv;
  protected $status;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'argv' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
