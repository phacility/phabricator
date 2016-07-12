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
  const TYPE_COMMANDLINE = 'cli';
  const TYPE_CLUSTER = 'clr';

  protected function getConfiguration() {
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

  public static function loadClusterTokenForUser(PhabricatorUser $user) {
    if (!$user->isLoggedIn()) {
      return null;
    }

    $tokens = id(new PhabricatorConduitTokenQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($user->getPHID()))
      ->withTokenTypes(array(self::TYPE_CLUSTER))
      ->withExpired(false)
      ->execute();

    // Only return a token if it has at least 5 minutes left before
    // expiration. Cluster tokens cycle regularly, so we don't want to use
    // one that's going to expire momentarily.
    $now = PhabricatorTime::getNow();
    $must_expire_after = $now + phutil_units('5 minutes in seconds');

    foreach ($tokens as $token) {
      if ($token->getExpires() > $must_expire_after) {
        return $token;
      }
    }

    // We didn't find any existing tokens (or the existing tokens are all about
    // to expire) so generate a new token.

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $token = self::initializeNewToken(
        $user->getPHID(),
        self::TYPE_CLUSTER);
      $token->save();
    unset($unguarded);

    return $token;
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
      self::TYPE_COMMANDLINE => pht('Command Line API Token'),
      self::TYPE_CLUSTER => pht('Cluster API Token'),
    );

    return idx($map, $type, $type);
  }

  public static function getAllTokenTypes() {
    return array(
      self::TYPE_STANDARD,
      self::TYPE_COMMANDLINE,
      self::TYPE_CLUSTER,
    );
  }

  private function getTokenExpires($token_type) {
    $now = PhabricatorTime::getNow();
    switch ($token_type) {
      case self::TYPE_STANDARD:
        return null;
      case self::TYPE_COMMANDLINE:
        return $now + phutil_units('1 hour in seconds');
      case self::TYPE_CLUSTER:
        return $now + phutil_units('30 minutes in seconds');
      default:
        throw new Exception(
          pht('Unknown Conduit token type "%s"!', $token_type));
    }
  }

  public function getPublicTokenName() {
    switch ($this->getTokenType()) {
      case self::TYPE_CLUSTER:
        return pht('Cluster API Token');
      default:
        return substr($this->getToken(), 0, 8).'...';
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
