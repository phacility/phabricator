<?php

final class PhabricatorExternalAccount extends PhabricatorUserDAO {

  protected $userPHID;
  protected $accountType;
  protected $accountDomain;
  protected $accountSecret;
  protected $accountID;
  protected $displayName;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XUSR);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

}
