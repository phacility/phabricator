<?php

final class PhabricatorRepositoryAuditRequest extends PhabricatorRepositoryDAO {

  protected $auditorPHID;
  protected $commitPHID;
  protected $auditReasons = array();
  protected $auditStatus;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'auditReasons' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
