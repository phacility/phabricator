<?php

final class PhabricatorPasswordAuthProvider extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('Username/Password');
  }

  public function getConfigurationHelp() {
    return pht(
      "(WARNING) Examine the table below for information on how password ".
      "hashes will be stored in the database.\n\n".
      "(NOTE) You can select a minimum password length by setting ".
      "`%s` in configuration.",
      'account.minimum-password-length');
  }

  public function renderConfigurationFooter() {
    $hashers = PhabricatorPasswordHasher::getAllHashers();
    $hashers = msort($hashers, 'getStrength');
    $hashers = array_reverse($hashers);

    $yes = phutil_tag(
      'strong',
      array(
        'style' => 'color: #009900',
      ),
      pht('Yes'));

    $no = phutil_tag(
      'strong',
      array(
        'style' => 'color: #990000',
      ),
      pht('Not Installed'));

    $best_hasher_name = null;
    try {
      $best_hasher = PhabricatorPasswordHasher::getBestHasher();
      $best_hasher_name = $best_hasher->getHashName();
    } catch (PhabricatorPasswordHasherUnavailableException $ex) {
      // There are no suitable hashers. The user might be able to enable some,
      // so we don't want to fatal here. We'll fatal when users try to actually
      // use this stuff if it isn't fixed before then. Until then, we just
      // don't highlight a row. In practice, at least one hasher should always
      // be available.
    }

    $rows = array();
    $rowc = array();
    foreach ($hashers as $hasher) {
      $is_installed = $hasher->canHashPasswords();

      $rows[] = array(
        $hasher->getHumanReadableName(),
        $hasher->getHashName(),
        $hasher->getHumanReadableStrength(),
        ($is_installed ? $yes : $no),
        ($is_installed ? null : $hasher->getInstallInstructions()),
      );
      $rowc[] = ($best_hasher_name == $hasher->getHashName())
        ? 'highlighted'
        : null;
    }

    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        pht('Algorithm'),
        pht('Name'),
        pht('Strength'),
        pht('Installed'),
        pht('Install Instructions'),
      ));

    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'wide',
      ));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Password Hash Algorithms'))
      ->setSubheader(
        pht(
          'Stronger algorithms are listed first. The highlighted algorithm '.
          'will be used when storing new hashes. Older hashes will be '.
          'upgraded to the best algorithm over time.'));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);
  }

  public function getDescriptionForCreate() {
    return pht(
      'Allow users to log in or register using a username and password.');
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = new PhutilEmptyAuthAdapter();
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

  public function buildInviteForm(
    PhabricatorAuthStartController $controller) {
    $request = $controller->getRequest();
    $viewer = $request->getViewer();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('invite', true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Username'))
          ->setName('username'));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Register an Account'))
      ->appendForm($form)
      ->setSubmitURI('/auth/register/')
      ->addSubmitButton(pht('Continue'));

    return $dialog;
  }

  public function buildLinkForm(
    PhabricatorAuthLinkController $controller) {
    throw new Exception(pht("Password providers can't be linked."));
  }

  private function renderPasswordLoginForm(
    AphrontRequest $request,
    $require_captcha = false,
    $captcha_valid = false) {

    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setSubmitURI($this->getLoginURI())
      ->setUser($viewer)
      ->setTitle(pht('Log In'))
      ->addSubmitButton(pht('Log In'));

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
      $request->getCookie(PhabricatorCookies::COOKIE_USERNAME));

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
        $errors[] = pht(
          'Too many login failures recently. You must '.
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
      $errors = id(new PHUIInfoView())->setErrors($errors);
    }

    $form = id(new PHUIFormLayoutView())
      ->setFullWidth(true)
      ->appendChild($errors)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Username or Email'))
          ->setName('username')
          ->setValue($v_user)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('Password'))
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

    if ($request->isFormPost()) {
      if (!$require_captcha || $captcha_valid) {
        $username_or_email = $request->getStr('username');
        if (strlen($username_or_email)) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'username = %s',
            $username_or_email);

          if (!$user) {
            $user = PhabricatorUser::loadOneWithEmailAddress(
              $username_or_email);
          }

          if ($user) {
            $envelope = new PhutilOpaqueEnvelope($request->getStr('password'));
            if ($user->comparePassword($envelope)) {
              $account = $this->loadOrCreateAccount($user->getPHID());
              $log_user = $user;

              // If the user's password is stored using a less-than-optimal
              // hash, upgrade them to the strongest available hash.

              $hash_envelope = new PhutilOpaqueEnvelope(
                $user->getPasswordHash());
              if (PhabricatorPasswordHasher::canUpgradeHash($hash_envelope)) {
                $user->setPassword($envelope);

                $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
                  $user->save();
                unset($unguarded);
              }
            }
          }
        }
      }
    }

    if (!$account) {
      if ($request->isFormPost()) {
        $log = PhabricatorUserLog::initializeNewLog(
          null,
          $log_user ? $log_user->getPHID() : null,
          PhabricatorUserLog::ACTION_LOGIN_FAILURE);
        $log->save();
      }

      $request->clearCookie(PhabricatorCookies::COOKIE_USERNAME);

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
      if ($provider instanceof PhabricatorPasswordAuthProvider) {
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

  public function shouldAllowEmailTrustConfiguration() {
    return false;
  }
}
