<?php

abstract class PhabricatorAuthFactor extends Phobject {

  abstract public function getFactorName();
  abstract public function getFactorKey();
  abstract public function getFactorCreateHelp();
  abstract public function getFactorDescription();
  abstract public function processAddFactorForm(
    PhabricatorAuthFactorProvider $provider,
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user);

  abstract public function renderValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    AphrontFormView $form,
    PhabricatorUser $viewer,
    PhabricatorAuthFactorResult $validation_result);

  public function getParameterName(
    PhabricatorAuthFactorConfig $config,
    $name) {
    return 'authfactor.'.$config->getID().'.'.$name;
  }

  public static function getAllFactors() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFactorKey')
      ->execute();
  }

  protected function newConfigForUser(PhabricatorUser $user) {
    return id(new PhabricatorAuthFactorConfig())
      ->setUserPHID($user->getPHID());
  }

  protected function newResult() {
    return new PhabricatorAuthFactorResult();
  }

  public function newIconView() {
    return id(new PHUIIconView())
      ->setIcon('fa-mobile');
  }

  public function canCreateNewProvider() {
    return true;
  }

  public function getProviderCreateDescription() {
    return null;
  }

  public function canCreateNewConfiguration(PhabricatorUser $user) {
    return true;
  }

  public function getConfigurationCreateDescription(PhabricatorUser $user) {
    return null;
  }

  public function getFactorOrder() {
    return 1000;
  }

  final public function newSortVector() {
    return id(new PhutilSortVector())
      ->addInt($this->canCreateNewProvider() ? 0 : 1)
      ->addInt($this->getFactorOrder())
      ->addString($this->getFactorName());
  }

  protected function newChallenge(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer) {

    $engine = $config->getSessionEngine();

    return PhabricatorAuthChallenge::initializeNewChallenge()
      ->setUserPHID($viewer->getPHID())
      ->setSessionPHID($viewer->getSession()->getPHID())
      ->setFactorPHID($config->getPHID())
      ->setWorkflowKey($engine->getWorkflowKey());
  }

  abstract public function getRequestHasChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    AphrontRequest $response);

  final public function getNewIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {
    assert_instances_of($challenges, 'PhabricatorAuthChallenge');

    $now = PhabricatorTime::getNow();

    $new_challenges = $this->newIssuedChallenges(
      $config,
      $viewer,
      $challenges);

    assert_instances_of($new_challenges, 'PhabricatorAuthChallenge');

    foreach ($new_challenges as $new_challenge) {
      $ttl = $new_challenge->getChallengeTTL();
      if (!$ttl) {
        throw new Exception(
          pht('Newly issued MFA challenges must have a valid TTL!'));
      }

      if ($ttl < $now) {
        throw new Exception(
          pht(
            'Newly issued MFA challenges must have a future TTL. This '.
            'factor issued a bad TTL ("%s"). (Did you use a relative '.
            'time instead of an epoch?)',
            $ttl));
      }
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      foreach ($new_challenges as $challenge) {
        $challenge->save();
      }
    unset($unguarded);

    return $new_challenges;
  }

  abstract protected function newIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges);

  final public function getResultFromIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {
    assert_instances_of($challenges, 'PhabricatorAuthChallenge');

    $result = $this->newResultFromIssuedChallenges(
      $config,
      $viewer,
      $challenges);

    if ($result === null) {
      return $result;
    }

    if (!($result instanceof PhabricatorAuthFactorResult)) {
      throw new Exception(
        pht(
          'Expected "newResultFromIssuedChallenges()" to return null or '.
          'an object of class "%s"; got something else (in "%s").',
          'PhabricatorAuthFactorResult',
          get_class($this)));
    }

    $result->setIssuedChallenges($challenges);

    return $result;
  }

  abstract protected function newResultFromIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges);

  final public function getResultFromChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges) {
    assert_instances_of($challenges, 'PhabricatorAuthChallenge');

    $result = $this->newResultFromChallengeResponse(
      $config,
      $viewer,
      $request,
      $challenges);

    if (!($result instanceof PhabricatorAuthFactorResult)) {
      throw new Exception(
        pht(
          'Expected "newResultFromChallengeResponse()" to return an object '.
          'of class "%s"; got something else (in "%s").',
          'PhabricatorAuthFactorResult',
          get_class($this)));
    }

    $result->setIssuedChallenges($challenges);

    return $result;
  }

  abstract protected function newResultFromChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges);

  final protected function newAutomaticControl(
    PhabricatorAuthFactorResult $result) {

    $is_answered = (bool)$result->getAnsweredChallenge();
    if ($is_answered) {
      return $this->newAnsweredControl($result);
    }

    $is_wait = $result->getIsWait();
    if ($is_wait) {
      return $this->newWaitControl($result);
    }

    return null;
  }

  private function newWaitControl(
    PhabricatorAuthFactorResult $result) {

    $error = $result->getErrorMessage();

    $icon = id(new PHUIIconView())
      ->setIcon('fa-clock-o', 'red');

    return id(new PHUIFormTimerControl())
      ->setIcon($icon)
      ->appendChild($error)
      ->setError(pht('Wait'));
  }

  private function newAnsweredControl(
    PhabricatorAuthFactorResult $result) {

    $icon = id(new PHUIIconView())
      ->setIcon('fa-check-circle-o', 'green');

    return id(new PHUIFormTimerControl())
      ->setIcon($icon)
      ->appendChild(
        pht('You responded to this challenge correctly.'));
  }


/* -(  Synchronizing New Factors  )------------------------------------------ */


  final protected function loadMFASyncToken(
    AphrontRequest $request,
    AphrontFormView $form,
    PhabricatorUser $user) {

    // If the form included a synchronization key, load the corresponding
    // token. The user must synchronize to a key we generated because this
    // raises the barrier to theoretical attacks where an attacker might
    // provide a known key for factors like TOTP.

    // (We store and verify the hash of the key, not the key itself, to limit
    // how useful the data in the table is to an attacker.)

    $sync_type = PhabricatorAuthMFASyncTemporaryTokenType::TOKENTYPE;
    $sync_token = null;

    $sync_key = $request->getStr($this->getMFASyncTokenFormKey());
    if (strlen($sync_key)) {
      $sync_key_digest = PhabricatorHash::digestWithNamedKey(
        $sync_key,
        PhabricatorAuthMFASyncTemporaryTokenType::DIGEST_KEY);

      $sync_token = id(new PhabricatorAuthTemporaryTokenQuery())
        ->setViewer($user)
        ->withTokenResources(array($user->getPHID()))
        ->withTokenTypes(array($sync_type))
        ->withExpired(false)
        ->withTokenCodes(array($sync_key_digest))
        ->executeOne();
    }

    if (!$sync_token) {

      // Don't generate a new sync token if there are too many outstanding
      // tokens already. This is mostly relevant for push factors like SMS,
      // where generating a token has the side effect of sending a user a
      // message.

      $outstanding_limit = 10;
      $outstanding_tokens = id(new PhabricatorAuthTemporaryTokenQuery())
        ->setViewer($user)
        ->withTokenResources(array($user->getPHID()))
        ->withTokenTypes(array($sync_type))
        ->withExpired(false)
        ->execute();
      if (count($outstanding_tokens) > $outstanding_limit) {
        throw new Exception(
          pht(
            'Your account has too many outstanding, incomplete MFA '.
            'synchronization attempts. Wait an hour and try again.'));
      }

      $now = PhabricatorTime::getNow();

      $sync_key = Filesystem::readRandomCharacters(32);
      $sync_key_digest = PhabricatorHash::digestWithNamedKey(
        $sync_key,
        PhabricatorAuthMFASyncTemporaryTokenType::DIGEST_KEY);
      $sync_ttl = $this->getMFASyncTokenTTL();

      $sync_token = id(new PhabricatorAuthTemporaryToken())
        ->setIsNewTemporaryToken(true)
        ->setTokenResource($user->getPHID())
        ->setTokenType($sync_type)
        ->setTokenCode($sync_key_digest)
        ->setTokenExpires($now + $sync_ttl);

      // Note that property generation is unguarded, since factors that push
      // a challenge generally need to perform a write there.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $properties = $this->newMFASyncTokenProperties($user);

        foreach ($properties as $key => $value) {
          $sync_token->setTemporaryTokenProperty($key, $value);
        }

        $sync_token->save();
      unset($unguarded);
    }

    $form->addHiddenInput($this->getMFASyncTokenFormKey(), $sync_key);

    return $sync_token;
  }

  protected function newMFASyncTokenProperties(PhabricatorUser $user) {
    return array();
  }

  private function getMFASyncTokenFormKey() {
    return 'sync.key';
  }

  private function getMFASyncTokenTTL() {
    return phutil_units('1 hour in seconds');
  }

}
