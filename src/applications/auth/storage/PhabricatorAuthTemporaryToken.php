<?php

final class PhabricatorAuthTemporaryToken extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  // TODO: OAuth1 stores a client identifier here, which is not a real PHID.
  // At some point, we should rename this column to be a little more generic.
  protected $objectPHID;

  protected $tokenType;
  protected $tokenExpires;
  protected $tokenCode;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getTokenReadableTypeName() {
    // Eventually, it would be nice to let applications implement token types
    // so we can put this in modular subclasses.
    switch ($this->tokenType) {
      case PhabricatorAuthSessionEngine::ONETIME_TEMPORARY_TOKEN_TYPE:
        return pht('One-Time Login Token');
      case PhabricatorAuthSessionEngine::PASSWORD_TEMPORARY_TOKEN_TYPE:
        return pht('Password Reset Token');
    }

    return $this->tokenType;
  }

  public function isRevocable() {
    if ($this->tokenExpires < time()) {
      return false;
    }

    switch ($this->tokenType) {
      case PhabricatorAuthSessionEngine::ONETIME_TEMPORARY_TOKEN_TYPE:
      case PhabricatorAuthSessionEngine::PASSWORD_TEMPORARY_TOKEN_TYPE:
        return true;
    }

    return false;
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // We're just implement this interface to get access to the standard
    // query infrastructure.
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
