<?php

final class PhabricatorExternalAccount extends PhabricatorUserDAO {

  private $userPHID;
  private $accountType;
  private $accountDomain;
  private $accountSecret;
  private $accountID;
  private $displayName;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XUSR);
  }
}
