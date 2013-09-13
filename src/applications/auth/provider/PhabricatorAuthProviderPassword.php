<?php

final class PhabricatorAuthProviderPassword
  extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('Username/Password');
  }

  public function getConfigurationHelp() {
    return pht(
      'You can select a minimum password length by setting '.
      '`account.minimum-password-length` in configuration.');
  }

  public function getDescriptionForCreate() {
    return pht(
      'Allow users to login or register using a username and password.');
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = new PhutilAuthAdapterEmpty();
      $adapter->setAdapterType('password');
      $adapter->setAdapterDomain('self');
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  public function getLoginOrder() {
    // Make sure username/password appears first if it is enabled.
    return '100-'.$this->getProviderName();
  }

  public function shouldAllowAccountLink() {
    return false;
  }

  public function shouldAllowAccountUnlink() {
    return false;
  }

  public function isDefaultRegistrationProvider() {
    return true;
  }

  public function buildLoginForm(
    PhabricatorAuthStartController $controller) {
    $request = $controller->getRequest();
    return $this->renderPasswordLoginForm($request);
  }

  public function buildLinkForm(
    PhabricatorAuthLinkController $controller) {
    throw new Exception("Password providers can't be linked.");
  }

  private function renderPasswordLoginForm(
    AphrontRequest $request,
    $require_captcha = false,
    $captcha_valid = false) {

    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setSubmitURI($this->getLoginURI())
      ->setUser($viewer)
      ->setTitle(pht('Login to Phabricator'))
      ->addSubmitButton(pht('Login'));

    if ($this->shouldAllowRegistration()) {
      $dialog->addCancelButton(
        '/auth/register/',
        pht('Register New Account'));
    }

    $dialog->addFooter(
      phutil_tag(
        'a',
        array(
          'href' => '/login/email/',
        ),
        pht('Forgot your password?')));

    $v_user = nonempty(
      $request->getStr('username'),
      $request->getCookie('phusr'));

    $e_user = null;
    $e_pass = null;
    $e_captcha = null;

    $errors = array();
    if ($require_captcha && !$captcha_valid) {
      if (AphrontFormRecaptchaControl::hasCaptchaResponse($request)) {
        $e_captcha = pht('Invalid');
        $errors[] = pht('CAPTCHA was not entered correctly.');
      } else {
        $e_captcha = pht('Required');
        $errors[] = pht('Too many login failures recently. You must '.
                    'submit a CAPTCHA with your login request.');
      }
    } else if ($request->isHTTPPost()) {
      // NOTE: This is intentionally vague so as not to disclose whether a
      // given username or email is registered.
      $e_user = pht('Invalid');
      $e_pass = pht('Invalid');
      $errors[] = pht('Username or password are incorrect.');
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $form = id(new PHUIFormLayoutView())
      ->setFullWidth(true)
      ->appendChild($errors)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username or Email')
          ->setName('username')
          ->setValue($v_user)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Password')
          ->setName('password')
          ->setError($e_pass));

    if ($require_captcha) {
        $form->appendChild(
          id(new AphrontFormRecaptchaControl())
            ->setError($e_captcha));
    }

    $dialog->appendChild($form);

    return $dialog;
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $viewer = $request->getUser();

    $require_captcha = false;
    $captcha_valid = false;
    if (AphrontFormRecaptchaControl::isRecaptchaEnabled()) {
      $failed_attempts = PhabricatorUserLog::loadRecentEventsFromThisIP(
        PhabricatorUserLog::ACTION_LOGIN_FAILURE,
        60 * 15);
      if (count($failed_attempts) > 5) {
        $require_captcha = true;
        $captcha_valid = AphrontFormRecaptchaControl::processCaptcha($request);
      }
    }

    $response = null;
    $account = null;
    $log_user = null;

    if (!$require_captcha || $captcha_valid) {
      $username_or_email = $request->getStr('username');
      if (strlen($username_or_email)) {
        $user = id(new PhabricatorUser())->loadOneWhere(
          'username = %s',
          $username_or_email);

        if (!$user) {
          $user = PhabricatorUser::loadOneWithEmailAddress($username_or_email);
        }

        if ($user) {
          $envelope = new PhutilOpaqueEnvelope($request->getStr('password'));
          if ($user->comparePassword($envelope)) {
            $account = $this->loadOrCreateAccount($user->getPHID());
            $log_user = $user;
          }
        }
      }
    }

    if (!$account) {
      $log = PhabricatorUserLog::newLog(
        null,
        $log_user,
        PhabricatorUserLog::ACTION_LOGIN_FAILURE);
      $log->save();

      $request->clearCookie('phusr');
      $request->clearCookie('phsid');

      $response = $controller->buildProviderPageResponse(
        $this,
        $this->renderPasswordLoginForm(
          $request,
          $require_captcha,
          $captcha_valid));
    }

    return array($account, $response);
  }

  public function shouldRequireRegistrationPassword() {
    return true;
  }

  public function getDefaultExternalAccount() {
    $adapter = $this->getAdapter();

    return id(new PhabricatorExternalAccount())
      ->setAccountType($adapter->getAdapterType())
      ->setAccountDomain($adapter->getAdapterDomain());
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    parent::willSaveAccount($account);
    $account->setUserPHID($account->getAccountID());
  }

  public function willRegisterAccount(PhabricatorExternalAccount $account) {
    parent::willRegisterAccount($account);
    $account->setAccountID($account->getUserPHID());
  }

  public static function getPasswordProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorAuthProviderPassword) {
        return $provider;
      }
    }

    return null;
  }

  public function willRenderLinkedAccount(
    PhabricatorUser $viewer,
    PHUIObjectItemView $item,
    PhabricatorExternalAccount $account) {
    return;
  }

  public function shouldAllowAccountRefresh() {
    return false;
  }

}
