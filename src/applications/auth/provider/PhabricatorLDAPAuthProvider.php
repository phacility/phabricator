<?php

final class PhabricatorLDAPAuthProvider extends PhabricatorAuthProvider {

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

      $search_attributes = $conf->getProperty(self::KEY_SEARCH_ATTRIBUTES);
      $search_attributes = phutil_split_lines($search_attributes, false);
      $search_attributes = array_filter($search_attributes);

      $adapter = id(new PhutilLDAPAuthAdapter())
        ->setHostname(
          $conf->getProperty(self::KEY_HOSTNAME))
        ->setPort(
          $conf->getProperty(self::KEY_PORT))
        ->setBaseDistinguishedName(
          $conf->getProperty(self::KEY_DISTINGUISHED_NAME))
        ->setSearchAttributes($search_attributes)
        ->setUsernameAttribute(
          $conf->getProperty(self::KEY_USERNAME_ATTRIBUTE))
        ->setRealNameAttributes($realname_attributes)
        ->setLDAPVersion(
          $conf->getProperty(self::KEY_VERSION))
        ->setLDAPReferrals(
          $conf->getProperty(self::KEY_REFERRALS))
        ->setLDAPStartTLS(
          $conf->getProperty(self::KEY_START_TLS))
        ->setAlwaysSearch($conf->getProperty(self::KEY_ALWAYS_SEARCH))
        ->setAnonymousUsername(
          $conf->getProperty(self::KEY_ANONYMOUS_USERNAME))
        ->setAnonymousPassword(
          new PhutilOpaqueEnvelope(
            $conf->getProperty(self::KEY_ANONYMOUS_PASSWORD)))
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
          ->setLabel(pht('LDAP Username'))
          ->setName('ldap_username')
          ->setValue($v_user)
          ->setError($e_user))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('LDAP Password'))
          ->setName('ldap_password')
          ->setError($e_pass));

    if ($errors) {
      $errors = id(new PHUIInfoView())->setErrors($errors);
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

    if ($request->isFormPost()) {
      try {
        if (strlen($username) && $has_password) {
          $adapter = $this->getAdapter();
          $adapter->setLoginUsername($username);
          $adapter->setLoginPassword($password);

          // TODO: This calls ldap_bind() eventually, which dumps cleartext
          // passwords to the error log. See note in PhutilLDAPAuthAdapter.
          // See T3351.

          DarkConsoleErrorLogPluginAPI::enableDiscardMode();
            $account_id = $adapter->getAccountID();
          DarkConsoleErrorLogPluginAPI::disableDiscardMode();
        } else {
          throw new Exception(pht('Username and password are required!'));
        }
      } catch (PhutilAuthCredentialException $ex) {
        $response = $controller->buildProviderPageResponse(
          $this,
          $this->renderLoginForm($request, 'login'));
        return array($account, $response);
      } catch (Exception $ex) {
        // TODO: Make this cleaner.
        throw $ex;
      }
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }


  const KEY_HOSTNAME                = 'ldap:host';
  const KEY_PORT                    = 'ldap:port';
  const KEY_DISTINGUISHED_NAME      = 'ldap:dn';
  const KEY_SEARCH_ATTRIBUTES       = 'ldap:search-attribute';
  const KEY_USERNAME_ATTRIBUTE      = 'ldap:username-attribute';
  const KEY_REALNAME_ATTRIBUTES     = 'ldap:realname-attributes';
  const KEY_VERSION                 = 'ldap:version';
  const KEY_REFERRALS               = 'ldap:referrals';
  const KEY_START_TLS               = 'ldap:start-tls';
  const KEY_ANONYMOUS_USERNAME      = 'ldap:anoynmous-username';
  const KEY_ANONYMOUS_PASSWORD      = 'ldap:anonymous-password';
  const KEY_ALWAYS_SEARCH           = 'ldap:always-search';
  const KEY_ACTIVEDIRECTORY_DOMAIN  = 'ldap:activedirectory-domain';

  private function getPropertyKeys() {
    return array_keys($this->getPropertyLabels());
  }

  private function getPropertyLabels() {
    return array(
      self::KEY_HOSTNAME => pht('LDAP Hostname'),
      self::KEY_PORT => pht('LDAP Port'),
      self::KEY_DISTINGUISHED_NAME => pht('Base Distinguished Name'),
      self::KEY_SEARCH_ATTRIBUTES => pht('Search Attributes'),
      self::KEY_ALWAYS_SEARCH => pht('Always Search'),
      self::KEY_ANONYMOUS_USERNAME => pht('Anonymous Username'),
      self::KEY_ANONYMOUS_PASSWORD => pht('Anonymous Password'),
      self::KEY_USERNAME_ATTRIBUTE => pht('Username Attribute'),
      self::KEY_REALNAME_ATTRIBUTES => pht('Realname Attributes'),
      self::KEY_VERSION => pht('LDAP Version'),
      self::KEY_REFERRALS => pht('Enable Referrals'),
      self::KEY_START_TLS => pht('Use TLS'),
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

  public static function assertLDAPExtensionInstalled() {
    if (!function_exists('ldap_bind')) {
      throw new Exception(
        pht(
          'Before you can set up or use LDAP, you need to install the PHP '.
          'LDAP extension. It is not currently installed, so PHP can not '.
          'talk to LDAP. Usually you can install it with '.
          '`%s`, `%s`, or a similar package manager command.',
          'yum install php-ldap',
          'apt-get install php5-ldap'));
    }
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    self::assertLDAPExtensionInstalled();

    $labels = $this->getPropertyLabels();

    $captions = array(
      self::KEY_HOSTNAME =>
        pht('Example: %s%sFor LDAPS, use: %s',
          phutil_tag('tt', array(), pht('ldap.example.com')),
          phutil_tag('br'),
          phutil_tag('tt', array(), pht('ldaps://ldaps.example.com/'))),
      self::KEY_DISTINGUISHED_NAME =>
        pht('Example: %s',
          phutil_tag('tt', array(), pht('ou=People, dc=example, dc=com'))),
      self::KEY_USERNAME_ATTRIBUTE =>
        pht('Example: %s',
          phutil_tag('tt', array(), pht('sn'))),
      self::KEY_REALNAME_ATTRIBUTES =>
        pht('Example: %s',
          phutil_tag('tt', array(), pht('firstname, lastname'))),
      self::KEY_REFERRALS =>
        pht('Follow referrals. Disable this for Windows AD 2003.'),
      self::KEY_START_TLS =>
        pht('Start TLS after binding to the LDAP server.'),
      self::KEY_ALWAYS_SEARCH =>
        pht('Always bind and search, even without a username and password.'),
    );

    $types = array(
      self::KEY_REFERRALS           => 'checkbox',
      self::KEY_START_TLS           => 'checkbox',
      self::KEY_SEARCH_ATTRIBUTES   => 'textarea',
      self::KEY_REALNAME_ATTRIBUTES => 'list',
      self::KEY_ANONYMOUS_PASSWORD  => 'password',
      self::KEY_ALWAYS_SEARCH       => 'checkbox',
    );

    $instructions = array(
      self::KEY_SEARCH_ATTRIBUTES   => pht(
        "When a user types their LDAP username and password into Phabricator, ".
        "Phabricator can either bind to LDAP with those credentials directly ".
        "(which is simpler, but not as powerful) or bind to LDAP with ".
        "anonymous credentials, then search for record matching the supplied ".
        "credentials (which is more complicated, but more powerful).\n\n".
        "For many installs, direct binding is sufficient. However, you may ".
        "want to search first if:\n\n".
        "  - You want users to be able to login with either their username ".
        "    or their email address.\n".
        "  - The login/username is not part of the distinguished name in ".
        "    your LDAP records.\n".
        "  - You want to restrict logins to a subset of users (like only ".
        "    those in certain departments).\n".
        "  - Your LDAP server is configured in some other way that prevents ".
        "    direct binding from working correctly.\n\n".
        "**To bind directly**, enter the LDAP attribute corresponding to the ".
        "login name into the **Search Attributes** box below. Often, this is ".
        "something like `sn` or `uid`. This is the simplest configuration, ".
        "but will only work if the username is part of the distinguished ".
        "name, and won't let you apply complex restrictions to logins.\n\n".
        "  lang=text,name=Simple Direct Binding\n".
        "  sn\n\n".
        "**To search first**, provide an anonymous username and password ".
        "below (or check the **Always Search** checkbox), then enter one ".
        "or more search queries into this field, one per line. ".
        "After binding, these queries will be used to identify the ".
        "record associated with the login name the user typed.\n\n".
        "Searches will be tried in order until a matching record is found. ".
        "Each query can be a simple attribute name (like `sn` or `mail`), ".
        "which will search for a matching record, or it can be a complex ".
        "query that uses the string `\${login}` to represent the login ".
        "name.\n\n".
        "A common simple configuration is just an attribute name, like ".
        "`sn`, which will work the same way direct binding works:\n\n".
        "  lang=text,name=Simple Example\n".
        "  sn\n\n".
        "A slightly more complex configuration might let the user login with ".
        "either their login name or email address:\n\n".
        "  lang=text,name=Match Several Attributes\n".
        "  mail\n".
        "  sn\n\n".
        "If your LDAP directory is more complex, or you want to perform ".
        "sophisticated filtering, you can use more complex queries. Depending ".
        "on your directory structure, this example might allow users to login ".
        "with either their email address or username, but only if they're in ".
        "specific departments:\n\n".
        "  lang=text,name=Complex Example\n".
        "  (&(mail=\${login})(|(departmentNumber=1)(departmentNumber=2)))\n".
        "  (&(sn=\${login})(|(departmentNumber=1)(departmentNumber=2)))\n\n".
        "All of the attribute names used here are just examples: your LDAP ".
        "server may use different attribute names."),
      self::KEY_ALWAYS_SEARCH => pht(
        'To search for an LDAP record before authenticating, either check '.
        'the **Always Search** checkbox or enter an anonymous '.
        'username and password to use to perform the search.'),
      self::KEY_USERNAME_ATTRIBUTE => pht(
        'Optionally, specify a username attribute to use to prefill usernames '.
        'when registering a new account. This is purely cosmetic and does not '.
        'affect the login process, but you can configure it to make sure '.
        'users get the same default username as their LDAP username, so '.
        'usernames remain consistent across systems.'),
      self::KEY_REALNAME_ATTRIBUTES => pht(
        'Optionally, specify one or more comma-separated attributes to use to '.
        'prefill the "Real Name" field when registering a new account. This '.
        'is purely cosmetic and does not affect the login process, but can '.
        'make registration a little easier.'),
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
            ->setDisableAutocomplete(true)
            ->setValue($value);
          break;
        case 'textarea':
          $control = id(new AphrontFormTextAreaControl())
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

      $instruction_text = idx($instructions, $key);
      if (strlen($instruction_text)) {
        $form->appendRemarkupInstructions($instruction_text);
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

      if ($old === null || $old === '') {
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
      if ($provider instanceof PhabricatorLDAPAuthProvider) {
        return $provider;
      }
    }

    return null;
  }

}
