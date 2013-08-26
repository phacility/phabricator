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

  public function getDefaultProviderConfig() {
    return parent::getDefaultProviderConfig()
      ->setProperty(self::KEY_PORT, 389)
      ->setProperty(self::KEY_VERSION, 3);
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $conf = $this->getProviderConfig();

      $realname_attributes = $conf->getProperty(self::KEY_REALNAME_ATTRIBUTES);
      if (!is_array($realname_attributes)) {
        $realname_attributes = array();
      }

      $adapter = id(new PhutilAuthAdapterLDAP())
        ->setHostname(
          $conf->getProperty(self::KEY_HOSTNAME))
        ->setPort(
          $conf->getProperty(self::KEY_PORT))
        ->setBaseDistinguishedName(
          $conf->getProperty(self::KEY_DISTINGUISHED_NAME))
        ->setSearchAttribute(
          $conf->getProperty(self::KEY_SEARCH_ATTRIBUTE))
        ->setUsernameAttribute(
          $conf->getProperty(self::KEY_USERNAME_ATTRIBUTE))
        ->setRealNameAttributes($realname_attributes)
        ->setLDAPVersion(
          $conf->getProperty(self::KEY_VERSION))
        ->setLDAPReferrals(
          $conf->getProperty(self::KEY_REFERRALS))
        ->setLDAPStartTLS(
          $conf->getProperty(self::KEY_START_TLS))
        ->setAnonymousUsername(
          $conf->getProperty(self::KEY_ANONYMOUS_USERNAME))
        ->setAnonymousPassword(
          new PhutilOpaqueEnvelope(
            $conf->getProperty(self::KEY_ANONYMOUS_PASSWORD)))
        ->setSearchFirst(
          $conf->getProperty(self::KEY_SEARCH_FIRST))
        ->setActiveDirectoryDomain(
          $conf->getProperty(self::KEY_ACTIVEDIRECTORY_DOMAIN));
      $this->adapter = $adapter;
    }
    return $this->adapter;
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
    } else if ($mode == 'refresh') {
      $dialog->setTitle(pht('Refresh LDAP Account'));
      $dialog->addSubmitButton(pht('Refresh Account'));
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

    $form = id(new PHUIFormLayoutView())
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


  const KEY_HOSTNAME                = 'ldap:host';
  const KEY_PORT                    = 'ldap:port';
  const KEY_DISTINGUISHED_NAME      = 'ldap:dn';
  const KEY_SEARCH_ATTRIBUTE        = 'ldap:search-attribute';
  const KEY_USERNAME_ATTRIBUTE      = 'ldap:username-attribute';
  const KEY_REALNAME_ATTRIBUTES     = 'ldap:realname-attributes';
  const KEY_VERSION                 = 'ldap:version';
  const KEY_REFERRALS               = 'ldap:referrals';
  const KEY_START_TLS               = 'ldap:start-tls';
  const KEY_ANONYMOUS_USERNAME      = 'ldap:anoynmous-username';
  const KEY_ANONYMOUS_PASSWORD      = 'ldap:anonymous-password';
  const KEY_SEARCH_FIRST            = 'ldap:search-first';
  const KEY_ACTIVEDIRECTORY_DOMAIN  = 'ldap:activedirectory-domain';

  private function getPropertyKeys() {
    return array_keys($this->getPropertyLabels());
  }

  private function getPropertyLabels() {
    return array(
      self::KEY_HOSTNAME => pht('LDAP Hostname'),
      self::KEY_PORT => pht('LDAP Port'),
      self::KEY_DISTINGUISHED_NAME => pht('Base Distinguished Name'),
      self::KEY_SEARCH_ATTRIBUTE => pht('Search Attribute'),
      self::KEY_USERNAME_ATTRIBUTE => pht('Username Attribute'),
      self::KEY_REALNAME_ATTRIBUTES => pht('Realname Attributes'),
      self::KEY_VERSION => pht('LDAP Version'),
      self::KEY_REFERRALS => pht('Enable Referrals'),
      self::KEY_START_TLS => pht('Use TLS'),
      self::KEY_SEARCH_FIRST => pht('Search First'),
      self::KEY_ANONYMOUS_USERNAME => pht('Anonymous Username'),
      self::KEY_ANONYMOUS_PASSWORD => pht('Anonymous Password'),
      self::KEY_ACTIVEDIRECTORY_DOMAIN => pht('ActiveDirectory Domain'),
    );
  }

  public function readFormValuesFromProvider() {
    $properties = array();
    foreach ($this->getPropertyLabels() as $key => $ignored) {
      $properties[$key] = $this->getProviderConfig()->getProperty($key);
    }
    return $properties;
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    $values = array();
    foreach ($this->getPropertyKeys() as $key) {
      switch ($key) {
        case self::KEY_REALNAME_ATTRIBUTES:
          $values[$key] = $request->getStrList($key, array());
          break;
        default:
          $values[$key] = $request->getStr($key);
          break;
      }
    }

    return $values;
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {
    $errors = array();
    $issues = array();
    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $labels = $this->getPropertyLabels();

    $captions = array(
      self::KEY_HOSTNAME =>
        pht('Example: %s',
          hsprintf('<tt>%s</tt>', pht('ldap.example.com'))),
      self::KEY_DISTINGUISHED_NAME =>
        pht('Example: %s',
          hsprintf('<tt>%s</tt>', pht('ou=People, dc=example, dc=com'))),
      self::KEY_SEARCH_ATTRIBUTE =>
        pht('Example: %s',
          hsprintf('<tt>%s</tt>', pht('sn'))),
      self::KEY_USERNAME_ATTRIBUTE =>
        pht('Optional, if different from search attribute.'),
      self::KEY_REALNAME_ATTRIBUTES =>
        pht('Optional. Example: %s',
          hsprintf('<tt>%s</tt>', pht('firstname, lastname'))),
      self::KEY_REFERRALS =>
        pht('Follow referrals. Disable this for Windows AD 2003.'),
      self::KEY_START_TLS =>
        pht('Start TLS after binding to the LDAP server.'),
      self::KEY_SEARCH_FIRST =>
        pht(
          'When the user enters their username, search for a matching '.
          'record using the "Search Attribute", then try to bind using '.
          'the DN for the record. This is useful if usernames are not '.
          'part of the record DN.'),
      self::KEY_ANONYMOUS_USERNAME =>
        pht('Username to bind with before searching.'),
      self::KEY_ANONYMOUS_PASSWORD =>
        pht('Password to bind with before searching.'),
    );

    $types = array(
      self::KEY_REFERRALS           => 'checkbox',
      self::KEY_START_TLS           => 'checkbox',
      self::KEY_SEARCH_FIRST        => 'checkbox',
      self::KEY_REALNAME_ATTRIBUTES => 'list',
      self::KEY_ANONYMOUS_PASSWORD  => 'password',
    );

    foreach ($labels as $key => $label) {
      $caption = idx($captions, $key);
      $type = idx($types, $key);
      $value = idx($values, $key);

      $control = null;
      switch ($type) {
        case 'checkbox':
          $control = id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              $key,
              1,
              hsprintf('<strong>%s:</strong> %s', $label, $caption),
              $value);
          break;
        case 'list':
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value ? implode(', ', $value) : null);
          break;
        case 'password':
          $control = id(new AphrontFormPasswordControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
        default:
          $control = id(new AphrontFormTextControl())
            ->setName($key)
            ->setLabel($label)
            ->setCaption($caption)
            ->setValue($value);
          break;
      }

      $form->appendChild($control);
    }
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    $labels = $this->getPropertyLabels();
    if (isset($labels[$key])) {
      $label = $labels[$key];

      $mask = false;
      switch ($key) {
        case self::KEY_ANONYMOUS_PASSWORD:
          $mask = true;
          break;
      }

      if ($mask) {
        return pht(
          '%s updated the "%s" value.',
          $xaction->renderHandleLink($author_phid),
          $label);
      }

      if (!strlen($old)) {
        return pht(
          '%s set the "%s" value to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $new);
      } else {
        return pht(
          '%s changed the "%s" value from "%s" to "%s".',
          $xaction->renderHandleLink($author_phid),
          $label,
          $old,
          $new);
      }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  public static function getLDAPProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorAuthProviderLDAP) {
        return $provider;
      }
    }

    return null;
  }

}
