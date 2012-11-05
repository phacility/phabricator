<?php

final class DrydockLog extends DrydockDAO {

  protected $resourceID;
  protected $leaseID;
  protected $epoch;
  protected $message;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
