<?php

final class PhabricatorDuoAuthFactor
  extends PhabricatorAuthFactor {

  const PROP_CREDENTIAL = 'duo.credentialPHID';
  const PROP_ENROLL = 'duo.enroll';
  const PROP_USERNAMES = 'duo.usernames';
  const PROP_HOSTNAME = 'duo.hostname';

  public function getFactorKey() {
    return 'duo';
  }

  public function getFactorName() {
    return pht('Duo Security');
  }

  public function getFactorShortName() {
    return pht('Duo');
  }

  public function getFactorCreateHelp() {
    return pht('Support for Duo push authentication.');
  }

  public function getFactorDescription() {
    return pht(
      'When you need to authenticate, a request will be pushed to the '.
      'Duo application on your phone.');
  }

  public function getEnrollDescription(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {
    return pht(
      'To add a Duo factor, first download and install the Duo application '.
      'on your phone. Once you have launched the application and are ready '.
      'to perform setup, click continue.');
  }

  public function canCreateNewConfiguration(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    if ($this->loadConfigurationsForProvider($provider, $user)) {
      return false;
    }

    return true;
  }

  public function getConfigurationCreateDescription(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    $messages = array();

    if ($this->loadConfigurationsForProvider($provider, $user)) {
      $messages[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            pht(
              'You already have Duo authentication attached to your account '.
              'for this provider.'),
          ));
    }

    return $messages;
  }

  public function getConfigurationListDetails(
    PhabricatorAuthFactorConfig $config,
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $viewer) {

    $duo_user = $config->getAuthFactorConfigProperty('duo.username');

    return pht('Duo Username: %s', $duo_user);
  }


  public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorAuthFactorProvider $provider) {

    $viewer = $engine->getViewer();

    $credential_phid = $provider->getAuthFactorProviderProperty(
      self::PROP_CREDENTIAL);

    $hostname = $provider->getAuthFactorProviderProperty(self::PROP_HOSTNAME);
    $usernames = $provider->getAuthFactorProviderProperty(self::PROP_USERNAMES);
    $enroll = $provider->getAuthFactorProviderProperty(self::PROP_ENROLL);

    $credential_type = PassphrasePasswordCredentialType::CREDENTIAL_TYPE;
    $provides_type = PassphrasePasswordCredentialType::PROVIDES_TYPE;

    $credentials = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIsDestroyed(false)
      ->withProvidesTypes(array($provides_type))
      ->execute();

    $xaction_hostname =
      PhabricatorAuthFactorProviderDuoHostnameTransaction::TRANSACTIONTYPE;
    $xaction_credential =
      PhabricatorAuthFactorProviderDuoCredentialTransaction::TRANSACTIONTYPE;
    $xaction_usernames =
      PhabricatorAuthFactorProviderDuoUsernamesTransaction::TRANSACTIONTYPE;
    $xaction_enroll =
      PhabricatorAuthFactorProviderDuoEnrollTransaction::TRANSACTIONTYPE;

    return array(
      id(new PhabricatorTextEditField())
        ->setLabel(pht('Duo API Hostname'))
        ->setKey('duo.hostname')
        ->setValue($hostname)
        ->setTransactionType($xaction_hostname)
        ->setIsRequired(true),
      id(new PhabricatorCredentialEditField())
        ->setLabel(pht('Duo API Credential'))
        ->setKey('duo.credential')
        ->setValue($credential_phid)
        ->setTransactionType($xaction_credential)
        ->setCredentialType($credential_type)
        ->setCredentials($credentials),
      id(new PhabricatorSelectEditField())
        ->setLabel(pht('Duo Username'))
        ->setKey('duo.usernames')
        ->setValue($usernames)
        ->setTransactionType($xaction_usernames)
        ->setOptions(
          array(
            'username' => pht(
              'Use %s Username',
              PlatformSymbols::getPlatformServerName()),
            'email' => pht('Use Primary Email Address'),
          )),
      id(new PhabricatorSelectEditField())
        ->setLabel(pht('Create Accounts'))
        ->setKey('duo.enroll')
        ->setValue($enroll)
        ->setTransactionType($xaction_enroll)
        ->setOptions(
          array(
            'deny' => pht('Require Existing Duo Account'),
            'allow' => pht('Create New Duo Account'),
          )),
    );
  }


  public function processAddFactorForm(
    PhabricatorAuthFactorProvider $provider,
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user) {

    $token = $this->loadMFASyncToken($provider, $request, $form, $user);
    if ($this->isAuthResult($token)) {
      $form->appendChild($this->newAutomaticControl($token));
      return;
    }

    $enroll = $token->getTemporaryTokenProperty('duo.enroll');
    $duo_id = $token->getTemporaryTokenProperty('duo.user-id');
    $duo_uri = $token->getTemporaryTokenProperty('duo.uri');
    $duo_user = $token->getTemporaryTokenProperty('duo.username');

    $is_external = ($enroll === 'external');
    $is_auto = ($enroll === 'auto');
    $is_blocked = ($enroll === 'blocked');

    if (!$token->getIsNewTemporaryToken()) {
      if ($is_auto) {
        return $this->newDuoConfig($user, $duo_user);
      } else if ($is_external || $is_blocked) {
        $parameters = array(
          'username' => $duo_user,
        );

        $result = $this->newDuoFuture($provider)
          ->setMethod('preauth', $parameters)
          ->resolve();

        $result_code = $result['response']['result'];
        switch ($result_code) {
          case 'auth':
          case 'allow':
            return $this->newDuoConfig($user, $duo_user);
          case 'enroll':
            if ($is_blocked) {
              // We'll render an equivalent static control below, so skip
              // rendering here. We explicitly don't want to give the user
              // an enroll workflow.
              break;
            }

            $duo_uri = $result['response']['enroll_portal_url'];

            $waiting_icon = id(new PHUIIconView())
              ->setIcon('fa-mobile', 'red');

            $waiting_control = id(new PHUIFormTimerControl())
              ->setIcon($waiting_icon)
              ->setError(pht('Not Complete'))
              ->appendChild(
                pht(
                  'You have not completed Duo enrollment yet. '.
                  'Complete enrollment, then click continue.'));

            $form->appendControl($waiting_control);
            break;
          default:
          case 'deny':
            break;
        }
      } else {
        $parameters = array(
          'user_id' => $duo_id,
          'activation_code' => $duo_uri,
        );

        $future = $this->newDuoFuture($provider)
          ->setMethod('enroll_status', $parameters);

        $result = $future->resolve();
        $response = $result['response'];

        switch ($response) {
          case 'success':
            return $this->newDuoConfig($user, $duo_user);
          case 'waiting':
            $waiting_icon = id(new PHUIIconView())
              ->setIcon('fa-mobile', 'red');

            $waiting_control = id(new PHUIFormTimerControl())
              ->setIcon($waiting_icon)
              ->setError(pht('Not Complete'))
              ->appendChild(
                pht(
                  'You have not activated this enrollment in the Duo '.
                  'application on your phone yet. Complete activation, then '.
                  'click continue.'));

            $form->appendControl($waiting_control);
            break;
          case 'invalid':
          default:
            throw new Exception(
              pht(
                'This Duo enrollment attempt is invalid or has '.
                'expired ("%s"). Cancel the workflow and try again.',
                $response));
        }
      }
    }

    if ($is_blocked) {
      $blocked_icon = id(new PHUIIconView())
        ->setIcon('fa-times', 'red');

      $blocked_control = id(new PHUIFormTimerControl())
        ->setIcon($blocked_icon)
        ->appendChild(
          pht(
            'Your Duo account ("%s") has not completed Duo enrollment. '.
            'Check your email and complete enrollment to continue.',
            phutil_tag('strong', array(), $duo_user)));

      $form->appendControl($blocked_control);
    } else if ($is_auto) {
      $auto_icon = id(new PHUIIconView())
        ->setIcon('fa-check', 'green');

      $auto_control = id(new PHUIFormTimerControl())
        ->setIcon($auto_icon)
        ->appendChild(
          pht(
            'Duo account ("%s") is fully enrolled.',
            phutil_tag('strong', array(), $duo_user)));

      $form->appendControl($auto_control);
    } else {
      $duo_button = phutil_tag(
        'a',
        array(
          'href' => $duo_uri,
          'class' => 'button button-grey',
          'target' => ($is_external ? '_blank' : null),
        ),
        pht('Enroll Duo Account: %s', $duo_user));

      $duo_button = phutil_tag(
        'div',
        array(
          'class' => 'mfa-form-enroll-button',
        ),
        $duo_button);

      if ($is_external) {
        $form->appendRemarkupInstructions(
          pht(
            'Complete enrolling your phone with Duo:'));

        $form->appendControl(
          id(new AphrontFormMarkupControl())
            ->setValue($duo_button));
      } else {

        $form->appendRemarkupInstructions(
          pht(
            'Scan this QR code with the Duo application on your mobile '.
            'phone:'));


        $qr_code = $this->newQRCode($duo_uri);
        $form->appendChild($qr_code);

        $form->appendRemarkupInstructions(
          pht(
            'If you are currently using your phone to view this page, '.
            'click this button to open the Duo application:'));

        $form->appendControl(
          id(new AphrontFormMarkupControl())
            ->setValue($duo_button));
      }

      $form->appendRemarkupInstructions(
        pht(
          'Once you have completed setup on your phone, click continue.'));
    }
  }


  protected function newMFASyncTokenProperties(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    $duo_user = $this->getDuoUsername($provider, $user);

    // Duo automatically normalizes usernames to lowercase. Just do that here
    // so that our value agrees more closely with Duo.
    $duo_user = phutil_utf8_strtolower($duo_user);

    $parameters = array(
      'username' => $duo_user,
    );

    $result = $this->newDuoFuture($provider)
      ->setMethod('preauth', $parameters)
      ->resolve();

    $external_uri = null;
    $result_code = $result['response']['result'];
    $status_message = $result['response']['status_msg'];
    switch ($result_code) {
      case 'auth':
      case 'allow':
        // If the user already has a Duo account, they don't need to do
        // anything.
        return array(
          'duo.enroll' => 'auto',
          'duo.username' => $duo_user,
        );
      case 'enroll':
        if (!$this->shouldAllowDuoEnrollment($provider)) {
          return array(
            'duo.enroll' => 'blocked',
            'duo.username' => $duo_user,
          );
        }

        $external_uri = $result['response']['enroll_portal_url'];

        // Otherwise, enrollment is permitted so we're going to continue.
        break;
      default:
      case 'deny':
        return $this->newResult()
          ->setIsError(true)
          ->setErrorMessage(
            pht(
              'Your Duo account ("%s") is not permitted to access this '.
              'system. Contact your Duo administrator for help. '.
              'The Duo preauth API responded with status message ("%s"): %s',
              $duo_user,
              $result_code,
              $status_message));
    }

    // Duo's "/enroll" API isn't repeatable for the same username. If we're
    // the first call, great: we can do inline enrollment, which is way more
    // user friendly. Otherwise, we have to send the user on an adventure.

    $parameters = array(
      'username' => $duo_user,
      'valid_secs' => phutil_units('1 hour in seconds'),
    );

    try {
      $result = $this->newDuoFuture($provider)
        ->setMethod('enroll', $parameters)
        ->resolve();
    } catch (HTTPFutureHTTPResponseStatus $ex) {
      return array(
        'duo.enroll' => 'external',
        'duo.username' => $duo_user,
        'duo.uri' => $external_uri,
      );
    }

    return array(
      'duo.enroll' => 'inline',
      'duo.uri' => $result['response']['activation_code'],
      'duo.username' => $duo_user,
      'duo.user-id' => $result['response']['user_id'],
    );
  }

  protected function newIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {

    // If we already issued a valid challenge for this workflow and session,
    // don't issue a new one.

    $challenge = $this->getChallengeForCurrentContext(
      $config,
      $viewer,
      $challenges);
    if ($challenge) {
      return array();
    }

    if (!$this->hasCSRF($config)) {
      return $this->newResult()
        ->setIsContinue(true)
        ->setErrorMessage(
          pht(
            'An authorization request will be pushed to the Duo '.
            'application on your phone.'));
    }

    $provider = $config->getFactorProvider();

    // Otherwise, issue a new challenge.
    $duo_user = (string)$config->getAuthFactorConfigProperty('duo.username');

    $parameters = array(
      'username' => $duo_user,
    );

    $response = $this->newDuoFuture($provider)
      ->setMethod('preauth', $parameters)
      ->resolve();
    $response = $response['response'];

    $next_step = $response['result'];
    $status_message = $response['status_msg'];
    switch ($next_step) {
      case 'auth':
        // We're good to go.
        break;
      case 'allow':
        // Duo is telling us to bypass MFA. For now, refuse.
        return $this->newResult()
          ->setIsError(true)
          ->setErrorMessage(
            pht(
              'Duo is not requiring a challenge, which defeats the '.
              'purpose of MFA. Duo must be configured to challenge you.'));
      case 'enroll':
        return $this->newResult()
          ->setIsError(true)
          ->setErrorMessage(
            pht(
              'Your Duo account ("%s") requires enrollment. Contact your '.
              'Duo administrator for help. Duo status message: %s',
              $duo_user,
              $status_message));
      case 'deny':
      default:
        return $this->newResult()
          ->setIsError(true)
          ->setErrorMessage(
            pht(
              'Your Duo account ("%s") is not permitted to access this '.
              'system. Contact your Duo administrator for help. The Duo '.
              'preauth API responded with status message ("%s"): %s',
              $duo_user,
              $next_step,
              $status_message));
    }

    $has_push = false;
    $devices = $response['devices'];
    foreach ($devices as $device) {
      $capabilities = array_fuse($device['capabilities']);
      if (isset($capabilities['push'])) {
        $has_push = true;
        break;
      }
    }

    if (!$has_push) {
      return $this->newResult()
        ->setIsError(true)
        ->setErrorMessage(
          pht(
            'This factor has been removed from your device, so this server '.
            'can not send you a challenge. To continue, an administrator '.
            'must strip this factor from your account.'));
    }

    $push_info = array(
      pht('Domain') => $this->getInstallDisplayName(),
    );
    $push_info = phutil_build_http_querystring($push_info);

    $parameters = array(
      'username' => $duo_user,
      'factor' => 'push',
      'async' => '1',

      // Duo allows us to specify a device, or to pass "auto" to have it pick
      // the first one. For now, just let it pick.
      'device' => 'auto',

      // This is a hard-coded prefix for the word "... request" in the Duo UI,
      // which defaults to "Login". We could pass richer information from
      // workflows here, but it's not very flexible anyway.
      'type' => 'Authentication',

      'display_username' => $viewer->getUsername(),
      'pushinfo' => $push_info,
    );

    $result = $this->newDuoFuture($provider)
      ->setMethod('auth', $parameters)
      ->resolve();

    $duo_xaction = $result['response']['txid'];

    // The Duo push timeout is 60 seconds. Set our challenge to expire slightly
    // more quickly so that we'll re-issue a new challenge before Duo times out.
    // This should keep users away from a dead-end where they can't respond to
    // Duo but we won't issue a new challenge yet.
    $ttl_seconds = 55;

    return array(
      $this->newChallenge($config, $viewer)
        ->setChallengeKey($duo_xaction)
        ->setChallengeTTL(PhabricatorTime::getNow() + $ttl_seconds),
    );
  }

  protected function newResultFromIssuedChallenges(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    array $challenges) {

    $challenge = $this->getChallengeForCurrentContext(
      $config,
      $viewer,
      $challenges);

    if ($challenge->getIsAnsweredChallenge()) {
      return $this->newResult()
        ->setAnsweredChallenge($challenge);
    }

    $provider = $config->getFactorProvider();
    $duo_xaction = $challenge->getChallengeKey();

    $parameters = array(
      'txid' => $duo_xaction,
    );

    // This endpoint always long-polls, so use a timeout to force it to act
    // more asynchronously.
    try {
      $result = $this->newDuoFuture($provider)
        ->setHTTPMethod('GET')
        ->setMethod('auth_status', $parameters)
        ->setTimeout(3)
        ->resolve();

      $state = $result['response']['result'];
      $status = $result['response']['status'];
    } catch (HTTPFutureCURLResponseStatus $exception) {
      if ($exception->isTimeout()) {
        $state = 'waiting';
        $status = 'poll';
      } else {
        throw $exception;
      }
    }

    $now = PhabricatorTime::getNow();

    switch ($state) {
      case 'allow':
        $ttl = PhabricatorTime::getNow()
          + phutil_units('15 minutes in seconds');

        $challenge
          ->markChallengeAsAnswered($ttl);

        return $this->newResult()
          ->setAnsweredChallenge($challenge);
      case 'waiting':
        // If we didn't just issue this challenge, give the user a stronger
        // hint that they need to follow the instructions.
        if (!$challenge->getIsNewChallenge()) {
          return $this->newResult()
            ->setIsContinue(true)
            ->setIcon(
              id(new PHUIIconView())
              ->setIcon('fa-exclamation-triangle', 'yellow'))
            ->setErrorMessage(
              pht(
                'You must approve the challenge which was sent to your '.
                'phone. Open the Duo application and confirm the challenge, '.
                'then continue.'));
        }

        // Otherwise, we'll construct a default message later on.
        break;
      default:
      case 'deny':
        if ($status === 'timeout') {
          return $this->newResult()
            ->setIsError(true)
            ->setErrorMessage(
              pht(
                'This request has timed out because you took too long to '.
                'respond.'));
        } else {
          $wait_duration = ($challenge->getChallengeTTL() - $now) + 1;

          return $this->newResult()
            ->setIsWait(true)
            ->setErrorMessage(
              pht(
                'You denied this request. Wait %s second(s) to try again.',
                new PhutilNumber($wait_duration)));
        }
        break;
    }

    return null;
  }

  public function renderValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    AphrontFormView $form,
    PhabricatorUser $viewer,
    PhabricatorAuthFactorResult $result) {

    $control = $this->newAutomaticControl($result);

    $control
      ->setLabel(pht('Duo'))
      ->setCaption(pht('Factor Name: %s', $config->getFactorName()));

    $form->appendChild($control);
  }

  public function getRequestHasChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    AphrontRequest $request) {
    return false;
  }

  protected function newResultFromChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges) {

    return $this->getResultForPrompt(
      $config,
      $viewer,
      $request,
      $challenges);
  }

  protected function newResultForPrompt(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges) {

    $result = $this->newResult()
      ->setIsContinue(true)
      ->setErrorMessage(
        pht(
          'A challenge has been sent to your phone. Open the Duo '.
          'application and confirm the challenge, then continue.'));

    $challenge = $this->getChallengeForCurrentContext(
      $config,
      $viewer,
      $challenges);
    if ($challenge) {
      $result
        ->setStatusChallenge($challenge)
        ->setIcon(
          id(new PHUIIconView())
            ->setIcon('fa-refresh', 'green ph-spin'));
    }

    return $result;
  }

  private function newDuoFuture(PhabricatorAuthFactorProvider $provider) {
    $credential_phid = $provider->getAuthFactorProviderProperty(
      self::PROP_CREDENTIAL);

    $omnipotent = PhabricatorUser::getOmnipotentUser();

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($omnipotent)
      ->withPHIDs(array($credential_phid))
      ->needSecrets(true)
      ->executeOne();
    if (!$credential) {
      throw new Exception(
        pht(
          'Unable to load Duo API credential ("%s").',
          $credential_phid));
    }

    $duo_key = $credential->getUsername();
    $duo_secret = $credential->getSecret();
    if (!$duo_secret) {
      throw new Exception(
        pht(
          'Duo API credential ("%s") has no secret key.',
          $credential_phid));
    }

    $duo_host = $provider->getAuthFactorProviderProperty(
      self::PROP_HOSTNAME);
    self::requireDuoAPIHostname($duo_host);

    return id(new PhabricatorDuoFuture())
      ->setIntegrationKey($duo_key)
      ->setSecretKey($duo_secret)
      ->setAPIHostname($duo_host)
      ->setTimeout(10)
      ->setHTTPMethod('POST');
  }

  private function getDuoUsername(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    $mode = $provider->getAuthFactorProviderProperty(self::PROP_USERNAMES);
    switch ($mode) {
      case 'username':
        return $user->getUsername();
      case 'email':
        return $user->loadPrimaryEmailAddress();
      default:
        throw new Exception(
          pht(
            'Duo username pairing mode ("%s") is not supported.',
            $mode));
    }
  }

  private function shouldAllowDuoEnrollment(
    PhabricatorAuthFactorProvider $provider) {

    $mode = $provider->getAuthFactorProviderProperty(self::PROP_ENROLL);
    switch ($mode) {
      case 'deny':
        return false;
      case 'allow':
        return true;
      default:
        throw new Exception(
          pht(
            'Duo enrollment mode ("%s") is not supported.',
            $mode));
    }
  }

  private function newDuoConfig(PhabricatorUser $user, $duo_user) {
    $config_properties = array(
      'duo.username' => $duo_user,
    );

    $config = $this->newConfigForUser($user)
      ->setFactorName(pht('Duo (%s)', $duo_user))
      ->setProperties($config_properties);

    return $config;
  }

  public static function requireDuoAPIHostname($hostname) {
    if (preg_match('/\.duosecurity\.com\z/', $hostname)) {
      return;
    }

    throw new Exception(
      pht(
        'Duo API hostname ("%s") is invalid, hostname must be '.
        '"*.duosecurity.com".',
        $hostname));
  }

  public function newChallengeStatusView(
    PhabricatorAuthFactorConfig $config,
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $viewer,
    PhabricatorAuthChallenge $challenge) {

    $duo_xaction = $challenge->getChallengeKey();

    $parameters = array(
      'txid' => $duo_xaction,
    );

    $default_result = id(new PhabricatorAuthChallengeUpdate())
      ->setRetry(true);

    try {
      $result = $this->newDuoFuture($provider)
        ->setHTTPMethod('GET')
        ->setMethod('auth_status', $parameters)
        ->setTimeout(5)
        ->resolve();

      $state = $result['response']['result'];
    } catch (HTTPFutureCURLResponseStatus $exception) {
      // If we failed or timed out, retry. Usually, this is a timeout.
      return id(new PhabricatorAuthChallengeUpdate())
        ->setRetry(true);
    }

    // For now, don't update the view for anything but an "Allow". Updates
    // here are just about providing more visual feedback for user convenience.
    if ($state !== 'allow') {
      return id(new PhabricatorAuthChallengeUpdate())
        ->setRetry(false);
    }

    $icon = id(new PHUIIconView())
      ->setIcon('fa-check-circle-o', 'green');

    $view = id(new PHUIFormTimerControl())
      ->setIcon($icon)
      ->appendChild(pht('You responded to this challenge correctly.'))
      ->newTimerView();

    return id(new PhabricatorAuthChallengeUpdate())
      ->setState('allow')
      ->setRetry(false)
      ->setMarkup($view);
  }

}
