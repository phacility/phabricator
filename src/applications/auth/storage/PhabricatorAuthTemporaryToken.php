<?php

final class PhabricatorAuthTemporaryToken extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  // TODO: OAuth1 stores a client identifier here, which is not a real PHID.
  // At some point, we should rename this column to be a little more generic.
  protected $objectPHID;

  protected $tokenType;
  protected $tokenExpires;
  protected $tokenCode;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'tokenType' => 'text64',
        'tokenExpires' => 'epoch',
        'tokenCode' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_token' => array(
          'columns' => array('objectPHID', 'tokenType', 'tokenCode'),
          'unique' => true,
        ),
        'key_expires' => array(
          'columns' => array('tokenExpires'),
        ),
      ),
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

  public function revokeToken() {
    if ($this->isRevocable()) {
      $this->setTokenExpires(PhabricatorTime::getNow() - 1)->save();
    }
    return $this;
  }

  public static function revokeTokens(
    PhabricatorUser $viewer,
    array $object_phids,
    array $token_types) {

    $tokens = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs($object_phids)
      ->withTokenTypes($token_types)
      ->withExpired(false)
      ->execute();

    foreach ($tokens as $token) {
      $token->revokeToken();
    }
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
