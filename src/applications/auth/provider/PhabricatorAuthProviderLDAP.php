<?php

final class PhabricatorAuthProviderLDAP
  extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('LDAP');
  }

  public function getDescriptionForCreate() {
    return pht(
      'Configure a connection to an LDAP server so that users can use their '.
      'LDAP credentials to log in to Phabricator.');
  }


  public function isEnabled() {
    return parent::isEnabled() &&
           PhabricatorEnv::getEnvConfig('ldap.auth-enabled');
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = id(new PhutilAuthAdapterLDAP())
        ->setHostname(PhabricatorEnv::getEnvConfig('ldap.hostname'))
        ->setPort(PhabricatorEnv::getEnvConfig('ldap.port'))
        ->setBaseDistinguishedName(PhabricatorEnv::getEnvConfig('ldap.base_dn'))
        ->setSearchAttribute(
            PhabricatorEnv::getEnvConfig('ldap.search_attribute'))
        ->setUsernameAttribute(
            PhabricatorEnv::getEnvConfig('ldap.username-attribute'))
        ->setLDAPVersion(PhabricatorEnv::getEnvConfig('ldap.version'))
        ->setLDAPReferrals(PhabricatorEnv::getEnvConfig('ldap.referrals'))
        ->setLDAPStartTLS(PhabricatorEnv::getEnvConfig('ldap.start-tls'))
        ->setAnonymousUsername(
            PhabricatorEnv::getEnvConfig('ldap.anonymous-user-name'))
        ->setAnonymousPassword(
            new PhutilOpaqueEnvelope(
              PhabricatorEnv::getEnvConfig('ldap.anonymous-user-password')))
        ->setSearchFirst(PhabricatorEnv::getEnvConfig('ldap.search-first'))
        ->setActiveDirectoryDomain(
            PhabricatorEnv::getEnvConfig('ldap.activedirectory_domain'));
      $this->adapter = $adapter;
    }
    return $this->adapter;
  }

  public function shouldAllowLogin() {
    return true;
  }

  public function shouldAllowRegistration() {
    return true;
  }

  public function shouldAllowAccountLink() {
    return true;
  }

  public function shouldAllowAccountUnlink() {
    return true;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setSubmitURI($this->getLoginURI())
      ->setUser($viewer);

    if ($mode == 'link') {
      $dialog->setTitle(pht('Link LDAP Account'));
      $dialog->addSubmitButton(pht('Link Accounts'));
      $dialog->addCancelButton($this->getSettingsURI());
    } else {
      if ($this->shouldAllowRegistration()) {
        $dialog->setTitle(pht('Login or Register with LDAP'));
        $dialog->addSubmitButton(pht('Login or Register'));
      } else {
        $dialog->setTitle(pht('Login with LDAP'));
        $dialog->addSubmitButton(pht('Login'));
      }
      if ($mode == 'login') {
        $dialog->addCancelButton($this->getStartURI());
      }
    }

    $v_user = $request->getStr('ldap_username');

    $e_user = null;
    $e_pass = null;

    $errors = array();
    if ($request->isHTTPPost()) {
      // NOTE: This is intentionally vague so as not to disclose whether a
      // given username exists.
      $e_user = pht('Invalid');
      $e_pass = pht('Invalid');
      $errors[] = pht('Username or password are incorrect.');
    }

    $form = id(new AphrontFormLayoutView())
      ->setUser($viewer)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('LDAP Username')
          ->setName('ldap_username')
          ->setValue($v_user)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('LDAP Password')
          ->setName('ldap_password')
          ->setError($e_pass));

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $dialog->appendChild($errors);
    $dialog->appendChild($form);


    return $dialog;
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $viewer = $request->getUser();
    $response = null;
    $account = null;

    $username = $request->getStr('ldap_username');
    $password = $request->getStr('ldap_password');
    $has_password = strlen($password);
    $password = new PhutilOpaqueEnvelope($password);

    if (!strlen($username) || !$has_password) {
      $response = $controller->buildProviderPageResponse(
        $this,
        $this->renderLoginForm($request, 'login'));
      return array($account, $response);
    }

    try {
      if (strlen($username) && $has_password) {
        $adapter = $this->getAdapter();
        $adapter->setLoginUsername($username);
        $adapter->setLoginPassword($password);

        // TODO: This calls ldap_bind() eventually, which dumps cleartext
        // passwords to the error log. See note in PhutilAuthAdapterLDAP.
        // See T3351.

        DarkConsoleErrorLogPluginAPI::enableDiscardMode();
          $account_id = $adapter->getAccountID();
        DarkConsoleErrorLogPluginAPI::disableDiscardMode();
      } else {
        throw new Exception("Username and password are required!");
      }
    } catch (Exception $ex) {
      // TODO: Make this cleaner.
      throw $ex;
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

}
