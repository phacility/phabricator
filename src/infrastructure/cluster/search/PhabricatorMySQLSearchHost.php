<?php

final class PhabricatorMySQLSearchHost
  extends PhabricatorSearchHost {

  public function setConfig($config) {
    $this->setRoles(idx($config, 'roles',
      array('read' => true, 'write' => true)));
    return $this;
  }

  public function getDisplayName() {
    return 'MySQL';
  }

  public function getStatusViewColumns() {
    return array(
        pht('Protocol') => 'mysql',
        pht('Roles') => implode(', ', array_keys($this->getRoles())),
    );
  }

  public function getProtocol() {
    return 'mysql';
  }

  public function getConnectionStatus() {
    PhabricatorDatabaseRef::queryAll();
    $ref = PhabricatorDatabaseRef::getMasterDatabaseRefForApplication('search');
    $status = $ref->getConnectionStatus();
    return $status;
  }

}
