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

  private function newTokenTypeImplementation() {
    $types = PhabricatorAuthTemporaryTokenType::getAllTypes();

    $type = idx($types, $this->tokenType);
    if ($type) {
      return clone $type;
    }

    return null;
  }

  public function getTokenReadableTypeName() {
    $type = $this->newTokenTypeImplementation();
    if ($type) {
      return $type->getTokenReadableTypeName($this);
    }

    return $this->tokenType;
  }

  public function isRevocable() {
    if ($this->tokenExpires < time()) {
      return false;
    }

    $type = $this->newTokenTypeImplementation();
    if ($type) {
      return $type->isTokenRevocable($this);
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
