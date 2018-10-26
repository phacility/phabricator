<?php

final class LiskIsolationTestDAO extends LiskDAO {

  protected $name;
  protected $phid;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('TISO');
  }

  protected function establishLiveConnection($mode) {
    throw new LiskIsolationTestDAOException(
      pht(
        'Isolation failure! DAO is attempting to connect to an external '.
        'resource!'));
  }

  protected function getDatabaseName() {
    return 'test';
  }

  public function getTableName() {
    return 'test';
  }

}
