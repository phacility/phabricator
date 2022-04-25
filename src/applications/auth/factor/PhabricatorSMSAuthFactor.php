<?php

final class PhabricatorSMSAuthFactor
  extends PhabricatorAuthFactor {

  public function getFactorKey() {
    return 'sms';
  }

  public function getFactorName() {
    return pht('Text Message (SMS)');
  }

  public function getFactorShortName() {
    return pht('SMS');
  }

  public function getFactorCreateHelp() {
    return pht(
      'Allow users to receive a code via SMS.');
  }

  public function getFactorDescription() {
    return pht(
      'When you need to authenticate, a text message with a code will '.
      'be sent to your phone.');
  }

  public function getFactorOrder() {
    // Sort this factor toward the end of the list because SMS is relatively
    // weak.
    return 2000;
  }

  public function isContactNumberFactor() {
    return true;
  }

  public function canCreateNewProvider() {
    return $this->isSMSMailerConfigured();
  }

  public function getProviderCreateDescription() {
    $messages = array();

    if (!$this->isSMSMailerConfigured()) {
      $messages[] = id(new PHUIInfoView())
        ->setErrors(
          array(
            pht(
              'You have not configured an outbound SMS mailer. You must '.
              'configure one before you can set up SMS. See: %s',
              phutil_tag(
                'a',
                array(
                  'href' => '/config/edit/cluster.mailers/',
                ),
                'cluster.mailers')),
          ));
    }

    $messages[] = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setErrors(
        array(
          pht(
            'SMS is weak, and relatively easy for attackers to compromise. '.
            'Strongly consider using a different MFA provider.'),
        ));

    return $messages;
  }

  public function canCreateNewConfiguration(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    if (!$this->loadUserContactNumber($user)) {
      return false;
    }

    if ($this->loadConfigurationsForProvider($provider, $user)) {
      return false;
    }

    return true;
  }

  public function getConfigurationCreateDescription(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {

    $messages = array();

    if (!$this->loadUserContactNumber($user)) {
      $messages[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            pht(
              'You have not configured a primary contact number. Configure '.
              'a contact number before adding SMS as an authentication '.
              'factor.'),
          ));
    }

    if ($this->loadConfigurationsForProvider($provider, $user)) {
      $messages[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            pht(
              'You already have SMS authentication attached to your account.'),
          ));
    }

    return $messages;
  }

  public function getEnrollDescription(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {
    return pht(
      'To verify your phone as an authentication factor, a text message with '.
      'a secret code will be sent to the phone number you have listed as '.
      'your primary contact number.');
  }

  public function getEnrollButtonText(
    PhabricatorAuthFactorProvider $provider,
    PhabricatorUser $user) {
    $contact_number = $this->loadUserContactNumber($user);

    return pht('Send SMS: %s', $contact_number->getDisplayName());
  }

  public function processAddFactorForm(
    PhabricatorAuthFactorProvider $provider,
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user) {

    $token = $this->loadMFASyncToken($provider, $request, $form, $user);
    $code = $request->getStr('sms.code');

    $e_code = true;
    if (!$token->getIsNewTemporaryToken()) {
      $expect_code = $token->getTemporaryTokenProperty('code');

      $okay = phutil_hashes_are_identical(
        $this->normalizeSMSCode($code),
        $this->normalizeSMSCode($expect_code));

      if ($okay) {
        $config = $this->newConfigForUser($user)
          ->setFactorName(pht('SMS'));

        return $config;
      } else {
        if (!strlen($code)) {
          $e_code = pht('Required');
        } else {
          $e_code = pht('Invalid');
        }
      }
    }

    $form->appendRemarkupInstructions(
      pht(
        'Enter the code from the text message which was sent to your '.
        'primary contact number.'));

    $form->appendChild(
      id(new PHUIFormNumberControl())
        ->setLabel(pht('SMS Code'))
        ->setName('sms.code')
        ->setValue($code)
        ->setError($e_code));
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

    if (!$this->loadUserContactNumber($viewer)) {
      return $this->newResult()
        ->setIsError(true)
        ->setErrorMessage(
          pht(
            'Your account has no primary contact number.'));
    }

    if (!$this->isSMSMailerConfigured()) {
      return $this->newResult()
        ->setIsError(true)
        ->setErrorMessage(
          pht(
            'No outbound mailer which can deliver SMS messages is '.
            'configured.'));
    }

    if (!$this->hasCSRF($config)) {
      return $this->newResult()
        ->setIsContinue(true)
        ->setErrorMessage(
          pht(
            'A text message with an authorization code will be sent to your '.
            'primary contact number.'));
    }

    // Otherwise, issue a new challenge.

    $challenge_code = $this->newSMSChallengeCode();
    $envelope = new PhutilOpaqueEnvelope($challenge_code);
    $this->sendSMSCodeToUser($envelope, $viewer);

    $ttl_seconds = phutil_units('15 minutes in seconds');

    return array(
      $this->newChallenge($config, $viewer)
        ->setChallengeKey($challenge_code)
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

    return null;
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
        ->setValue($value)
        ->setError($error);
    }

    $control
      ->setLabel(pht('SMS Code'))
      ->setCaption(pht('Factor Name: %s', $config->getFactorName()));

    $form->appendChild($control);
  }

  public function getRequestHasChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    AphrontRequest $request) {
    $value = $this->getChallengeResponseFromRequest($config, $request);
    return (bool)strlen($value);
  }

  protected function newResultFromChallengeResponse(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request,
    array $challenges) {

    $challenge = $this->getChallengeForCurrentContext(
      $config,
      $viewer,
      $challenges);

    $code = $this->getChallengeResponseFromRequest(
      $config,
      $request);

    $result = $this->newResult()
      ->setValue($code);

    if ($challenge->getIsAnsweredChallenge()) {
      return $result->setAnsweredChallenge($challenge);
    }

    if (phutil_hashes_are_identical($code, $challenge->getChallengeKey())) {
      $ttl = PhabricatorTime::getNow() + phutil_units('15 minutes in seconds');

      $challenge
        ->markChallengeAsAnswered($ttl);

      return $result->setAnsweredChallenge($challenge);
    }

    if (strlen($code)) {
      $error_message = pht('Invalid');
    } else {
      $error_message = pht('Required');
    }

    $result->setErrorMessage($error_message);

    return $result;
  }

  private function newSMSChallengeCode() {
    $value = Filesystem::readRandomInteger(0, 99999999);
    $value = sprintf('%08d', $value);
    return $value;
  }

  private function isSMSMailerConfigured() {
    $mailers = PhabricatorMetaMTAMail::newMailers(
      array(
        'outbound' => true,
        'media' => array(
          PhabricatorMailSMSMessage::MESSAGETYPE,
        ),
      ));

    return (bool)$mailers;
  }

  private function loadUserContactNumber(PhabricatorUser $user) {
    $contact_numbers = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($user->getPHID()))
      ->withStatuses(
        array(
          PhabricatorAuthContactNumber::STATUS_ACTIVE,
        ))
      ->withIsPrimary(true)
      ->execute();

    if (count($contact_numbers) !== 1) {
      return null;
    }

    return head($contact_numbers);
  }

  protected function newMFASyncTokenProperties(
    PhabricatorAuthFactorProvider $providerr,
    PhabricatorUser $user) {

    $sms_code = $this->newSMSChallengeCode();

    $envelope = new PhutilOpaqueEnvelope($sms_code);
    $this->sendSMSCodeToUser($envelope, $user);

    return array(
      'code' => $sms_code,
    );
  }

  private function sendSMSCodeToUser(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorUser $user) {
    return id(new PhabricatorMetaMTAMail())
      ->setMessageType(PhabricatorMailSMSMessage::MESSAGETYPE)
      ->addTos(array($user->getPHID()))
      ->setForceDelivery(true)
      ->setSensitiveContent(true)
      ->setBody(
        pht(
          '%s (%s) MFA Code: %s',
          PlatformSymbols::getPlatformServerName(),
          $this->getInstallDisplayName(),
          $envelope->openEnvelope()))
      ->save();
  }

  private function normalizeSMSCode($code) {
    return trim($code);
  }

}
