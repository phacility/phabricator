<?php

final class PhabricatorAuthChallenge
  extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $factorPHID;
  protected $sessionPHID;
  protected $workflowKey;
  protected $challengeKey;
  protected $challengeTTL;
  protected $responseDigest;
  protected $responseTTL;
  protected $isCompleted;
  protected $properties = array();

  private $responseToken;

  const TOKEN_DIGEST_KEY = 'auth.challenge.token';

  public static function initializeNewChallenge() {
    return id(new self())
      ->setIsCompleted(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'challengeKey' => 'text255',
        'challengeTTL' => 'epoch',
        'workflowKey' => 'text255',
        'responseDigest' => 'text255?',
        'responseTTL' => 'epoch?',
        'isCompleted' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_issued' => array(
          'columns' => array('userPHID', 'challengeTTL'),
        ),
        'key_collection' => array(
          'columns' => array('challengeTTL'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorAuthChallengePHIDType::TYPECONST;
  }

  public function getIsReusedChallenge() {
    if ($this->getIsCompleted()) {
      return true;
    }

    // TODO: A challenge is "reused" if it has been answered previously and
    // the request doesn't include proof that the client provided the answer.
    // Since we aren't tracking client responses yet, any answered challenge
    // is always a reused challenge for now.

    return $this->getIsAnsweredChallenge();
  }

  public function getIsAnsweredChallenge() {
    return (bool)$this->getResponseDigest();
  }

  public function markChallengeAsAnswered($ttl) {
    $token = Filesystem::readRandomCharacters(32);
    $token = new PhutilOpaqueEnvelope($token);

    return $this
      ->setResponseToken($token, $ttl)
      ->save();
  }

  public function markChallengeAsCompleted() {
    return $this
      ->setIsCompleted(true)
      ->save();
  }

  public function setResponseToken(PhutilOpaqueEnvelope $token, $ttl) {
    if (!$this->getUserPHID()) {
      throw new PhutilInvalidStateException('setUserPHID');
    }

    if ($this->responseToken) {
      throw new Exception(
        pht(
          'This challenge already has a response token; you can not '.
          'set a new response token.'));
    }

    $now = PhabricatorTime::getNow();
    if ($ttl < $now) {
      throw new Exception(
        pht(
          'Response TTL is invalid: TTLs must be an epoch timestamp '.
          'coresponding to a future time (did you use a relative TTL by '.
          'mistake?).'));
    }

    if (preg_match('/ /', $token->openEnvelope())) {
      throw new Exception(
        pht(
          'The response token for this challenge is invalid: response '.
          'tokens may not include spaces.'));
    }

    $digest = PhabricatorHash::digestWithNamedKey(
      $token->openEnvelope(),
      self::TOKEN_DIGEST_KEY);

    if ($this->responseDigest !== null) {
      if (!phutil_hashes_are_identical($digest, $this->responseDigest)) {
        throw new Exception(
          pht(
            'Invalid response token for this challenge: token digest does '.
            'not match stored digest.'));
      }
    } else {
      $this->responseDigest = $digest;
    }

    $this->responseToken = $token;
    $this->responseTTL = $ttl;

    return $this;
  }

  public function setResponseDigest($value) {
    throw new Exception(
      pht(
        'You can not set the response digest for a challenge directly. '.
        'Instead, set a response token. A response digest will be computed '.
        'automatically.'));
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return $this->properties[$key];
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() === $this->getUserPHID());
  }

}
