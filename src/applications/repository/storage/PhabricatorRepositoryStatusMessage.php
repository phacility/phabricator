<?php

final class PhabricatorRepositoryStatusMessage
  extends PhabricatorRepositoryDAO {

  const TYPE_INIT = 'init';
  const TYPE_FETCH = 'fetch';
  const TYPE_NEEDS_UPDATE = 'needs-update';

  const CODE_ERROR = 'error';
  const CODE_WORKING = 'working';
  const CODE_OKAY = 'okay';

  protected $repositoryID;
  protected $statusType;
  protected $statusCode;
  protected $parameters = array();
  protected $epoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'statusType' => 'text32',
        'statusCode' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'repositoryID' => array(
          'columns' => array('repositoryID', 'statusType'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

}
