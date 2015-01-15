<?php

final class PhabricatorAuthFactorConfig extends PhabricatorAuthDAO {

  protected $userPHID;
  protected $factorKey;
  protected $factorName;
  protected $factorSecret;
  protected $properties = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'factorKey' => 'text64',
        'factorName' => 'text',
        'factorSecret' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_user' => array(
          'columns' => array('userPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorAuthAuthFactorPHIDType::TYPECONST);
  }

  public function getImplementation() {
    return idx(PhabricatorAuthFactor::getAllFactors(), $this->getFactorKey());
  }

  public function requireImplementation() {
    $impl = $this->getImplementation();
    if (!$impl) {
      throw new Exception(
        pht(
          'Attempting to operate on multi-factor auth which has no '.
          'corresponding implementation (factor key is "%s").',
          $this->getFactorKey()));
    }

    return $impl;
  }

}
