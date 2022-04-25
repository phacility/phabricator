<?php

final class PhabricatorPhabricatorAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  const PROPERTY_PHABRICATOR_NAME = 'oauth2:phabricator:name';
  const PROPERTY_PHABRICATOR_URI  = 'oauth2:phabricator:uri';

  public function getProviderName() {
    return PlatformSymbols::getPlatformServerName();
  }

  public function getConfigurationHelp() {
    if ($this->isCreate()) {
      return pht(
        "**Step 1 of 2 - Name Remote Server**\n\n".
        'Choose a permanent name for the remote server you want to connect '.
        'to. This name is used internally to keep track of the remote '.
        'server, in case the URL changes later.');
    }

    return parent::getConfigurationHelp();
  }
  protected function getProviderConfigurationHelp() {
    $config = $this->getProviderConfig();
    $base_uri = rtrim(
      $config->getProperty(self::PROPERTY_PHABRICATOR_URI), '/');
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "**Step 2 of 2 - Configure OAuth Server**\n\n".
      "To configure OAuth, create a new application here:".
      "\n\n".
      "%s/oauthserver/client/create/".
      "\n\n".
      "When creating your application, use these settings:".
      "\n\n".
      "  - **Redirect URI:** Set this to: `%s`".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** to the fields above. (You may need to generate the ".
      "client secret by clicking 'New Secret' first.)",
      $base_uri,
      $login_uri);
  }

  protected function newOAuthAdapter() {
    $config = $this->getProviderConfig();
    return id(new PhutilPhabricatorAuthAdapter())
      ->setAdapterDomain($config->getProviderDomain())
      ->setPhabricatorBaseURI(
        $config->getProperty(self::PROPERTY_PHABRICATOR_URI));
  }

  protected function getLoginIcon() {
    return PlatformSymbols::getPlatformServerName();
  }

  private function isCreate() {
    return !$this->getProviderConfig()->getID();
  }

  public function readFormValuesFromProvider() {
    $config = $this->getProviderConfig();
    $uri = $config->getProperty(self::PROPERTY_PHABRICATOR_URI);

    return parent::readFormValuesFromProvider() + array(
      self::PROPERTY_PHABRICATOR_NAME => $this->getProviderDomain(),
      self::PROPERTY_PHABRICATOR_URI  => $uri,
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    $is_setup = $this->isCreate();
    if ($is_setup) {
      $parent_values = array();
      $name = $request->getStr(self::PROPERTY_PHABRICATOR_NAME);
    } else {
      $parent_values = parent::readFormValuesFromRequest($request);
      $name = $this->getProviderDomain();
    }

    return $parent_values + array(
      self::PROPERTY_PHABRICATOR_NAME => $name,
      self::PROPERTY_PHABRICATOR_URI =>
        $request->getStr(self::PROPERTY_PHABRICATOR_URI),
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $is_setup = $this->isCreate();

    if (!$is_setup) {
      list($errors, $issues, $values) =
        parent::processEditForm($request, $values);
    } else {
      $errors = array();
      $issues = array();
    }

    $key_name = self::PROPERTY_PHABRICATOR_NAME;
    $key_uri = self::PROPERTY_PHABRICATOR_URI;

    if (!strlen($values[$key_name])) {
      $errors[] = pht('Server name is required.');
      $issues[$key_name] = pht('Required');
    } else if (!preg_match('/^[a-z0-9.]+\z/', $values[$key_name])) {
      $errors[] = pht(
        'Server name must contain only lowercase letters, '.
        'digits, and periods.');
      $issues[$key_name] = pht('Invalid');
    }

    if (!strlen($values[$key_uri])) {
      $errors[] = pht('Base URI is required.');
      $issues[$key_uri] = pht('Required');
    } else {
      $uri = new PhutilURI($values[$key_uri]);
      if (!$uri->getProtocol()) {
        $errors[] = pht(
          'Base URI should include protocol (like "%s").',
          'https://');
        $issues[$key_uri] = pht('Invalid');
      }
    }

    if (!$errors && $is_setup) {
      $config = $this->getProviderConfig();

      $config->setProviderDomain($values[$key_name]);
    }

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $is_setup = $this->isCreate();

    $e_required = $request->isFormPost() ? null : true;

    $v_name = $values[self::PROPERTY_PHABRICATOR_NAME];
    if ($is_setup) {
      $e_name = idx($issues, self::PROPERTY_PHABRICATOR_NAME, $e_required);
    } else {
      $e_name = null;
    }

    $v_uri = $values[self::PROPERTY_PHABRICATOR_URI];
    $e_uri = idx($issues, self::PROPERTY_PHABRICATOR_URI, $e_required);

    if ($is_setup) {
      $form
       ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Server Name'))
            ->setValue($v_name)
            ->setName(self::PROPERTY_PHABRICATOR_NAME)
            ->setError($e_name)
            ->setCaption(pht(
            'Use lowercase letters, digits, and periods. For example: %s',
            phutil_tag(
              'tt',
              array(),
              '`example.oauthserver`'))));
    } else {
      $form
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Server Name'))
            ->setValue($v_name));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Base URI'))
          ->setValue($v_uri)
          ->setName(self::PROPERTY_PHABRICATOR_URI)
          ->setCaption(
            pht(
              'The URI where the OAuth server is installed. For example: %s',
              phutil_tag('tt', array(), 'https://devtools.example.com/')))
          ->setError($e_uri));

    if (!$is_setup) {
      parent::extendEditForm($request, $form, $values, $issues);
    }
  }

  public function hasSetupStep() {
    return true;
  }

  public function getPhabricatorURI() {
    $config = $this->getProviderConfig();
    return $config->getProperty(self::PROPERTY_PHABRICATOR_URI);
  }

}
