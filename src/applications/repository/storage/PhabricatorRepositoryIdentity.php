<?php

final class PhabricatorRepositoryIdentity
  extends PhabricatorRepositoryDAO {

  protected $identityNameHash;
  protected $identityNameRaw;
  protected $identityNameEncoding;

  protected $automaticGuessedUserPHID;
  protected $manuallySetUserPHID;
  protected $currentEffectiveUserPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_BINARY => array(
        'identityNameRaw' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'identityNameHash' => 'bytes12',
        'identityNameEncoding' => 'text16?',
        'automaticGuessedUserPHID' => 'phid?',
        'manuallySetUserPHID' => 'phid?',
        'currentEffectiveUserPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_identity' => array(
          'columns' => array('identityNameHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorRepositoryIdentityPHIDType::TYPECONST;
  }

}
