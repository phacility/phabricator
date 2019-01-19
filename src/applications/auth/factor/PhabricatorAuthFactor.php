<?php

abstract class PhabricatorAuthFactor extends Phobject {

  abstract public function getFactorName();
  abstract public function getFactorKey();
  abstract public function getFactorCreateHelp();
  abstract public function getFactorDescription();
  abstract public function processAddFactorForm(
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
      ->setUserPHID($user->getPHID())
      ->setFactorKey($this->getFactorKey());
  }

  protected function newResult() {
    return new PhabricatorAuthFactorResult();
  }

  public function newIconView() {
    return id(new PHUIIconView())
      ->setIcon('fa-mobile');
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


}
