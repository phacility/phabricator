<?php

final class PhabricatorTOTPAuthFactor extends PhabricatorAuthFactor {

  public function getFactorKey() {
    return 'totp';
  }

  public function getFactorName() {
    return pht('Mobile Phone App (TOTP)');
  }

  public function getFactorShortName() {
    return pht('TOTP');
  }

  public function getFactorCreateHelp() {
    return pht(
      'Allow users to attach a mobile authenticator application (like '.
      'Google Authenticator) to their account.');
  }

  public function getFactorDescription() {
    return pht(
      'Attach a mobile authenticator application (like Authy '.
      'or Google Authenticator) to your account. When you need to '.
      'authenticate, you will enter a code shown on your phone.');
  }

  public function getEnrollDescription(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    return pht(
      'To add a TOTP factor to your account, you will first need to install '.
      'a mobile authenticator application on your phone. Two applications '.
      'which work well are **Google Authenticator** and **Authy**, but any '.
      'other TOTP application should also work.'.
      "\n\n".
      'If you haven\'t already, download and install a TOTP application on '.
      'your phone now. Once you\'ve launched the application and are ready '.
      'to add a new TOTP code, continue to the next step.');
  }

  public function getConfigurationListDetails(
    PhabricatorAuthFactorConfig $config,
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $viewer) {

    $bits = strlen($config->getFactorSecret()) * 8;
    return pht('%d-Bit Secret', $bits);
  }

  public function processAddFactorForm(
    PhabricatorAuthFactorProvider $provider,
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user) {

    $sync_token = $this->loadMFASyncToken(
      $provider,
      $request,
      $form,
      $user);
    $secret = $sync_token->getTemporaryTokenProperty('secret');

    $code = $request->getStr('totpcode');

    $e_code = true;
    if (!$sync_token->getIsNewTemporaryToken()) {
      $okay = (bool)$this->getTimestepAtWhichResponseIsValid(
        $this->getAllowedTimesteps($this->getCurrentTimestep()),
        new PhutilOpaqueEnvelope($secret),
        $code);

      if ($okay) {
        $config = $this->newConfigForUser($user)
          ->setFactorName(pht('Mobile App (TOTP)'))
          ->setFactorSecret($secret)
          ->setMFASyncToken($sync_token);

        return $config;
      } else {
        if (!strlen($code)) {
          $e_code = pht('Required');
        } else {
          $e_code = pht('Invalid');
        }
      }
    }

    $form->appendInstructions(
      pht(
        'Scan the QR code or manually enter the key shown below into the '.
        'application.'));

    $prod_uri = new PhutilURI(PhabricatorEnv::getProductionURI('/'));
    $issuer = $prod_uri->getDomain();

    $uri = urisprintf(
      'otpauth://totp/%s:%s?secret=%s&issuer=%s',
      $issuer,
      $user->getUsername(),
      $secret,
      $issuer);

    $qrcode = $this->newQRCode($uri);
    $form->appendChild($qrcode);

    $form->appendChild(
      id(new AphrontFormStaticControl())
        ->setLabel(pht('Key'))
        ->setValue(phutil_tag('strong', array(), $secret)));

    $form->appendInstructions(
      pht(
        '(If given an option, select that this key is "Time Based", not '.
        '"Counter Based".)'));

    $form->appendInstructions(
      pht(
        'After entering the key, the application should display a numeric '.
        'code. Enter that code below to confirm that you have configured '.
        'the authenticator correctly:'));

    $form->appendChild(
      id(new PHUIFormNumberControl())
        ->setLabel(pht('TOTP Code'))
        ->setName('totpcode')
        ->setValue($code)
        ->setAutofocus(true)
        ->setError($e_code));

  }

  protected function newIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {

    $current_step = $this->getCurrentTimestep();

    // If we already issued a valid challenge, don't issue a new one.
    if ($challenges) {
      return array();
    }

    // Otherwise, generate a new challenge for the current timestep and compute
    // the TTL.

    // When computing the TTL, note that we accept codes within a certain
    // window of the challenge timestep to account for clock skew and users
    // needing time to enter codes.

    // We don't want this challenge to expire until after all valid responses
    // to it are no longer valid responses to any other challenge we might
    // issue in the future. If the challenge expires too quickly, we may issue
    // a new challenge which can accept the same TOTP code response.

    // This means that we need to keep this challenge alive for double the
    // window size: if we're currently at timestep 3, the user might respond
    // with the code for timestep 5. This is valid, since timestep 5 is within
    // the window for timestep 3.

    // But the code for timestep 5 can be used to respond at timesteps 3, 4, 5,
    // 6, and 7. To prevent any valid response to this challenge from being
    // used again, we need to keep this challenge active until timestep 8.

    $window_size = $this->getTimestepWindowSize();
    $step_duration = $this->getTimestepDuration();

    $ttl_steps = ($window_size * 2) + 1;
    $ttl_seconds = ($ttl_steps * $step_duration);

    return array(
      $this->newChallenge($config, $viewer)
        ->setChallengeKey($current_step)
        ->setChallengeTTL(PhabricatorTime::getNow() + $ttl_seconds),
    );
  }

  public function renderValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    AphrontFormView $form,
    PhabricatorUser $viewer,
    PhabricatorAuthFactorResult $result) {

    $control = $this->newAutomaticControl($result);
    if (!$control) {
      $value = $result->getValue();
      $error = $result->getErrorMessage();
      $name = $this->getChallengeResponseParameterName($config);

      $control = id(new PHUIFormNumberControl())
        ->setName($name)
        ->setDisableAutocomplete(true)
        ->setAutofocus(true)
        ->setValue($value)
        ->setError($error);
    }

    $control
      ->setLabel(pht('App Code'))
      ->setCaption(pht('Factor Name: %s', $config->getFactorName()));

    $form->appendChild($control);
  }

  public function getRequestHasChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    AphrontRequest $request) {

    $value = $this->getChallengeResponseFromRequest($config, $request);
    return (bool)strlen($value);
  }


  protected function newResultFromIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {

    // If we've already issued a challenge at the current timestep or any
    // nearby timestep, require that it was issued to the current session.
    // This is defusing attacks where you (broadly) look at someone's phone
    // and type the code in more quickly than they do.
    $session_phid = $viewer->getSession()->getPHID();
    $now = PhabricatorTime::getNow();

    $engine = $config->getSessionEngine();
    $workflow_key = $engine->getWorkflowKey();

    $current_timestep = $this->getCurrentTimestep();

    foreach ($challenges as $challenge) {
      $challenge_timestep = (int)$challenge->getChallengeKey();
      $wait_duration = ($challenge->getChallengeTTL() - $now) + 1;

      if ($challenge->getSessionPHID() !== $session_phid) {
        return $this->newResult()
          ->setIsWait(true)
          ->setErrorMessage(
            pht(
              'This factor recently issued a challenge to a different login '.
              'session. Wait %s second(s) for the code to cycle, then try '.
              'again.',
              new PhutilNumber($wait_duration)));
      }

      if ($challenge->getWorkflowKey() !== $workflow_key) {
        return $this->newResult()
          ->setIsWait(true)
          ->setErrorMessage(
            pht(
              'This factor recently issued a challenge for a different '.
              'workflow. Wait %s second(s) for the code to cycle, then try '.
              'again.',
              new PhutilNumber($wait_duration)));
      }

      // If the current realtime timestep isn't a valid response to the current
      // challenge but the challenge hasn't expired yet, we're locking out
      // the factor to prevent challenge windows from overlapping. Let the user
      // know that they should wait for a new challenge.
      $challenge_timesteps = $this->getAllowedTimesteps($challenge_timestep);
      if (!isset($challenge_timesteps[$current_timestep])) {
        return $this->newResult()
          ->setIsWait(true)
          ->setErrorMessage(
            pht(
              'This factor recently issued a challenge which has expired. '.
              'A new challenge can not be issued yet. Wait %s second(s) for '.
              'the code to cycle, then try again.',
              new PhutilNumber($wait_duration)));
      }

      if ($challenge->getIsReusedChallenge()) {
        return $this->newResult()
          ->setIsWait(true)
          ->setErrorMessage(
            pht(
              'You recently provided a response to this factor. Responses '.
              'may not be reused. Wait %s second(s) for the code to cycle, '.
              'then try again.',
              new PhutilNumber($wait_duration)));
      }
    }

    return null;
  }

  protected function newResultFromChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges) {

    $code = $this->getChallengeResponseFromRequest(
      $config,
      $request);

    $result = $this->newResult()
      ->setValue($code);

    // We expect to reach TOTP validation with exactly one valid challenge.
    if (count($challenges) !== 1) {
      throw new Exception(
        pht(
          'Reached TOTP challenge validation with an unexpected number of '.
          'unexpired challenges (%d), expected exactly one.',
          phutil_count($challenges)));
    }

    $challenge = head($challenges);

    // If the client has already provided a valid answer to this challenge and
    // submitted a token proving they answered it, we're all set.
    if ($challenge->getIsAnsweredChallenge()) {
      return $result->setAnsweredChallenge($challenge);
    }

    $challenge_timestep = (int)$challenge->getChallengeKey();
    $current_timestep = $this->getCurrentTimestep();

    $challenge_timesteps = $this->getAllowedTimesteps($challenge_timestep);
    $current_timesteps = $this->getAllowedTimesteps($current_timestep);

    // We require responses be both valid for the challenge and valid for the
    // current timestep. A longer challenge TTL doesn't let you use older
    // codes for a longer period of time.
    $valid_timestep = $this->getTimestepAtWhichResponseIsValid(
      array_intersect_key($challenge_timesteps, $current_timesteps),
      new PhutilOpaqueEnvelope($config->getFactorSecret()),
      $code);

    if ($valid_timestep) {
      $ttl = PhabricatorTime::getNow() + 60;

      $challenge
        ->setProperty('totp.timestep', $valid_timestep)
        ->markChallengeAsAnswered($ttl);

      $result->setAnsweredChallenge($challenge);
    } else {
      if (strlen($code)) {
        $error_message = pht('Invalid');
      } else {
        $error_message = pht('Required');
      }
      $result->setErrorMessage($error_message);
    }

    return $result;
  }

  public static function generateNewTOTPKey() {
    return strtoupper(Filesystem::readRandomCharacters(32));
  }

  public static function base32Decode($buf) {
    $buf = strtoupper($buf);

    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = str_split($map);
    $map = array_flip($map);

    $out = '';
    $len = strlen($buf);
    $acc = 0;
    $bits = 0;
    for ($ii = 0; $ii < $len; $ii++) {
      $chr = $buf[$ii];
      $val = $map[$chr];

      $acc = $acc << 5;
      $acc = $acc + $val;

      $bits += 5;
      if ($bits >= 8) {
        $bits = $bits - 8;
        $out .= chr(($acc & (0xFF << $bits)) >> $bits);
      }
    }

    return $out;
  }

  public static function getTOTPCode(PhutilOpaqueEnvelope $key, $timestamp) {
    $binary_timestamp = pack('N*', 0).pack('N*', $timestamp);
    $binary_key = self::base32Decode($key->openEnvelope());

    $hash = hash_hmac('sha1', $binary_timestamp, $binary_key, true);

    // See RFC 4226.

    $offset = ord($hash[19]) & 0x0F;

    $code = ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
            ((ord($hash[$offset + 3])       )      );

    $code = ($code % 1000000);
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);

    return $code;
  }

  private function getTimestepDuration() {
    return 30;
  }

  private function getCurrentTimestep() {
    $duration = $this->getTimestepDuration();
    return (int)(PhabricatorTime::getNow() / $duration);
  }

  private function getAllowedTimesteps($at_timestep) {
    $window = $this->getTimestepWindowSize();
    $range = range($at_timestep - $window, $at_timestep + $window);
    return array_fuse($range);
  }

  private function getTimestepWindowSize() {
    // The user is allowed to provide a code from the recent past or the
    // near future to account for minor clock skew between the client
    // and server, and the time it takes to actually enter a code.
    return 1;
  }

  private function getTimestepAtWhichResponseIsValid(
    array $timesteps,
    PhutilOpaqueEnvelope $key,
    $code) {

    foreach ($timesteps as $timestep) {
      $expect_code = self::getTOTPCode($key, $timestep);
      if (phutil_hashes_are_identical($code, $expect_code)) {
        return $timestep;
      }
    }

    return null;
  }

  protected function newMFASyncTokenProperties(
    PhabricatorAuthFactorProvider $providerr,
    PhabricatorUser $user) {
    return array(
      'secret' => self::generateNewTOTPKey(),
    );
  }

}
