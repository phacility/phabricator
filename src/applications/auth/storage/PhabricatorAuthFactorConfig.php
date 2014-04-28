<?php

final class PhabricatorAuthFactorConfig extends PhabricatorAuthDAO {

  protected $userPHID;
  protected $factorKey;
  protected $factorName;
  protected $factorSecret;
  protected $properties = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorAuthPHIDTypeAuthFactor::TYPECONST);
  }

  public function getImplementation() {
    return idx(PhabricatorAuthFactor::getAllFactors(), $this->getFactorKey());
  }

}
