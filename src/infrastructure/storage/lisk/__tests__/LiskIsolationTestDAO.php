<?php

final class LiskIsolationTestDAO extends LiskDAO {

  protected $name;
  protected $phid;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('TISO');
  }

  public function establishLiveConnection($mode) {
    throw new LiskIsolationTestDAOException(
      "Isolation failure! DAO is attempting to connect to an external ".
      "resource!");
  }

  public function getConnectionNamespace() {
    return 'test';
  }

  public function getTableName() {
    return 'test';
  }

}
