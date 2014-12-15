<?php

final class PhabricatorConduitToken
  extends PhabricatorConduitDAO
  implements PhabricatorPolicyInterface {

  protected $objectPHID;
  protected $tokenType;
  protected $token;
  protected $expires;

  private $object = self::ATTACHABLE;

  const TYPE_STANDARD = 'api';
  const TYPE_TEMPORARY = 'tmp';
  const TYPE_COMMANDLINE = 'cli';

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'tokenType' => 'text32',
        'token' => 'text32',
        'expires' => 'epoch?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'tokenType'),
        ),
        'key_token' => array(
          'columns' => array('token'),
          'unique' => true,
        ),
        'key_expires' => array(
          'columns' => array('expires'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewToken($object_phid, $token_type) {
    $token = new PhabricatorConduitToken();
    $token->objectPHID = $object_phid;
    $token->tokenType = $token_type;
    $token->expires = $token->getTokenExpires($token_type);

    $secret = $token_type.'-'.Filesystem::readRandomCharacters(32);
    $secret = substr($secret, 0, 32);
    $token->token = $secret;

    return $token;
  }

  public static function getTokenTypeName($type) {
    $map = array(
      self::TYPE_STANDARD => pht('Standard API Token'),
      self::TYPE_TEMPORARY => pht('Temporary API Token'),
      self::TYPE_COMMANDLINE => pht('Command Line API Token'),
    );

    return idx($map, $type, $type);
  }

  public static function getAllTokenTypes() {
    return array(
      self::TYPE_STANDARD,
      self::TYPE_TEMPORARY,
      self::TYPE_COMMANDLINE,
    );
  }

  private function getTokenExpires($token_type) {
    switch ($token_type) {
      case self::TYPE_STANDARD:
        return null;
      case self::TYPE_TEMPORARY:
        return PhabricatorTime::getNow() + phutil_units('24 hours in seconds');
      case self::TYPE_COMMANDLINE:
        return PhabricatorTime::getNow() + phutil_units('1 hour in seconds');
      default:
        throw new Exception(
          pht('Unknown Conduit token type "%s"!', $token_type));
    }
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject(PhabricatorUser $object) {
    $this->object = $object;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getObject()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Conduit tokens inherit the policies of the user they authenticate.');
  }

}
