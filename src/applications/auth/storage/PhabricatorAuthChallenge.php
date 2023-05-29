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
  private $isNewChallenge;

  const HTTPKEY = '__hisec.challenges__';
  const TOKEN_DIGEST_KEY = 'auth.challenge.token';

  public static function initializeNewChallenge() {
    return id(new self())
      ->setIsCompleted(0);
  }

  public static function newHTTPParametersFromChallenges(array $challenges) {
    assert_instances_of($challenges, __CLASS__);

    $token_list = array();
    foreach ($challenges as $challenge) {
      $token = $challenge->getResponseToken();
      if ($token) {
        $token_list[] = sprintf(
          '%s:%s',
          $challenge->getPHID(),
          $token->openEnvelope());
      }
    }

    if (!$token_list) {
      return array();
    }

    $token_list = implode(' ', $token_list);

    return array(
      self::HTTPKEY => $token_list,
    );
  }

  public static function newChallengeResponsesFromRequest(
    array $challenges,
    AphrontRequest $request) {
    assert_instances_of($challenges, __CLASS__);

    $token_list = $request->getStr(self::HTTPKEY);
    if ($token_list === null) {
      return;
    }
    $token_list = explode(' ', $token_list);

    $token_map = array();
    foreach ($token_list as $token_element) {
      $token_element = trim($token_element, ' ');

      if (!strlen($token_element)) {
        continue;
      }

      // NOTE: This error message is intentionally not printing the token to
      // avoid disclosing it. As a result, it isn't terribly useful, but no
      // normal user should ever end up here.
      if (!preg_match('/^[^:]+:/', $token_element)) {
        throw new Exception(
          pht(
            'This request included an improperly formatted MFA challenge '.
            'token and can not be processed.'));
      }

      list($phid, $token) = explode(':', $token_element, 2);

      if (isset($token_map[$phid])) {
        throw new Exception(
          pht(
            'This request improperly specifies an MFA challenge token ("%s") '.
            'multiple times and can not be processed.',
            $phid));
      }

      $token_map[$phid] = new PhutilOpaqueEnvelope($token);
    }

    $challenges = mpull($challenges, null, 'getPHID');

    $now = PhabricatorTime::getNow();
    foreach ($challenges as $challenge_phid => $challenge) {
      // If the response window has expired, don't attach the token.
      if ($challenge->getResponseTTL() < $now) {
        continue;
      }

      $token = idx($token_map, $challenge_phid);
      if (!$token) {
        continue;
      }

      $challenge->setResponseToken($token);
    }
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

    if (!$this->getIsAnsweredChallenge()) {
      return false;
    }

    // If the challenge has been answered but the client has provided a token
    // proving that they answered it, this is still a valid response.
    if ($this->getResponseToken()) {
      return false;
    }

    return true;
  }

  public function getIsAnsweredChallenge() {
    return (bool)$this->getResponseDigest();
  }

  public function markChallengeAsAnswered($ttl) {
    $token = Filesystem::readRandomCharacters(32);
    $token = new PhutilOpaqueEnvelope($token);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $this
      ->setResponseToken($token)
      ->setResponseTTL($ttl)
      ->save();

    unset($unguarded);

    return $this;
  }

  public function markChallengeAsCompleted() {
    return $this
      ->setIsCompleted(true)
      ->save();
  }

  public function setResponseToken(PhutilOpaqueEnvelope $token) {
    if (!$this->getUserPHID()) {
      throw new PhutilInvalidStateException('setUserPHID');
    }

    if ($this->responseToken) {
      throw new Exception(
        pht(
          'This challenge already has a response token; you can not '.
          'set a new response token.'));
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

    return $this;
  }

  public function getResponseToken() {
    return $this->responseToken;
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

  public function setIsNewChallenge($is_new_challenge) {
    $this->isNewChallenge = $is_new_challenge;
    return $this;
  }

  public function getIsNewChallenge() {
    return $this->isNewChallenge;
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
