<?php

final class PhabricatorAuthProviderLDAP
  extends PhabricatorAuthProvider {

  private $adapter;

  public function getProviderName() {
    return pht('LDAP');
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
    return false;
  }

  public function shouldAllowAccountUnlink() {
    return false;
  }

  public function buildLoginForm(
    PhabricatorAuthStartController $controller) {

    $request = $controller->getRequest();
    return $this->renderLoginForm($request);
  }

  private function renderLoginForm(AphrontRequest $request) {

    $viewer = $request->getUser();

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Login or Register'));

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Login with LDAP'));

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

    $form = id(new AphrontFormView())
      ->setAction($this->getLoginURI())
      ->setUser($viewer)
      ->setFlexible(true)
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
          ->setError($e_pass))
      ->appendChild($submit);

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    return array(
      $errors,
      $header,
      $form,
    );
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
        $this->renderLoginForm($request));
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
