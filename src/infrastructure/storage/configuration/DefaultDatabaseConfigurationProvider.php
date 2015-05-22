<?php

final class DefaultDatabaseConfigurationProvider
  implements DatabaseConfigurationProvider {

  private $dao;
  private $mode;
  private $namespace;

  public function __construct(
    LiskDAO $dao = null,
    $mode = 'r',
    $namespace = 'phabricator') {

    $this->dao = $dao;
    $this->mode = $mode;
    $this->namespace = $namespace;
  }

  public function getUser() {
    return PhabricatorEnv::getEnvConfig('mysql.user');
  }

  public function getPassword() {
    return new PhutilOpaqueEnvelope(PhabricatorEnv::getEnvConfig('mysql.pass'));
  }

  public function getHost() {
    return PhabricatorEnv::getEnvConfig('mysql.host');
  }

  public function getPort() {
    return PhabricatorEnv::getEnvConfig('mysql.port');
  }

  public function getDatabase() {
    if (!$this->getDao()) {
      return null;
    }
    return $this->namespace.'_'.$this->getDao()->getApplicationName();
  }

  protected function getDao() {
    return $this->dao;
  }

}
