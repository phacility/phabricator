<?php

abstract class PhabricatorSearchHost
  extends Phobject {

  const KEY_REFS = 'cluster.search.refs';
  const KEY_HEALTH = 'cluster.search.health';

  protected $engine;
  protected $healthRecord;
  protected $roles = array();

  protected $disabled;
  protected $host;
  protected $port;

  const STATUS_OKAY = 'okay';
  const STATUS_FAIL = 'fail';

  public function __construct(PhabricatorFulltextStorageEngine $engine) {
    $this->engine = $engine;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  /**
   * @return PhabricatorFulltextStorageEngine
   */
  public function getEngine() {
    return $this->engine;
  }

  public function isWritable() {
    return $this->hasRole('write');
  }

  public function isReadable() {
    return $this->hasRole('read');
  }

  public function hasRole($role) {
    return isset($this->roles[$role]) && $this->roles[$role] === true;
  }

  public function setRoles(array $roles) {
    foreach ($roles as $role => $val) {
      $this->roles[$role] = $val;
    }
    return $this;
  }

  public function getRoles() {
    $roles = array();
    foreach ($this->roles as $key => $val) {
      if ($val) {
        $roles[$key] = $val;
      }
    }
    return $roles;
  }

  public function setPort($value) {
    $this->port = $value;
    return $this;
  }

  public function getPort() {
    return $this->port;
  }

  public function setHost($value) {
    $this->host = $value;
    return $this;
  }

  public function getHost() {
    return $this->host;
  }


  public function getHealthRecordCacheKey() {
    $host = $this->getHost();
    $port = $this->getPort();
    $key = self::KEY_HEALTH;

    return "{$key}({$host}, {$port})";
  }

/**
 * @return PhabricatorClusterServiceHealthRecord
 */
  public function getHealthRecord() {
    if (!$this->healthRecord) {
      $this->healthRecord = new PhabricatorClusterServiceHealthRecord(
        $this->getHealthRecordCacheKey());
    }
    return $this->healthRecord;
  }

  public function didHealthCheck($reachable) {
    $record = $this->getHealthRecord();
    $should_check = $record->getShouldCheck();

    if ($should_check) {
      $record->didHealthCheck($reachable);
    }
  }

  /**
   * @return string[] Get a list of fields to show in the status overview UI
   */
  abstract public function getStatusViewColumns();

  abstract public function getConnectionStatus();

}
