<?php

abstract class PhabricatorAuthProvider extends Phobject {

  private $providerConfig;

  public function attachProviderConfig(PhabricatorAuthProviderConfig $config) {
    $this->providerConfig = $config;
    return $this;
  }

  public function hasProviderConfig() {
    return (bool)$this->providerConfig;
  }

  public function getProviderConfig() {
    if ($this->providerConfig === null) {
      throw new PhutilInvalidStateException('attachProviderConfig');
    }
    return $this->providerConfig;
  }

  public function getConfigurationHelp() {
    return null;
  }

  public function getDefaultProviderConfig() {
    return id(new PhabricatorAuthProviderConfig())
      ->setProviderClass(get_class($this))
      ->setIsEnabled(1)
      ->setShouldAllowLogin(1)
      ->setShouldAllowRegistration(1)
      ->setShouldAllowLink(1)
      ->setShouldAllowUnlink(1);
  }

  public function getNameForCreate() {
    return $this->getProviderName();
  }

  public function getDescriptionForCreate() {
    return null;
  }

  public function getProviderKey() {
    return $this->getAdapter()->getAdapterKey();
  }

  public function getProviderType() {
    return $this->getAdapter()->getAdapterType();
  }

  public function getProviderDomain() {
    return $this->getAdapter()->getAdapterDomain();
  }

  public static function getAllBaseProviders() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  public static function getAllProviders() {
    static $providers;

    if ($providers === null) {
      $objects = self::getAllBaseProviders();

      $configs = id(new PhabricatorAuthProviderConfigQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->execute();

      $providers = array();
      foreach ($configs as $config) {
        if (!isset($objects[$config->getProviderClass()])) {
          // This configuration is for a provider which is not installed.
          continue;
        }

        $object = clone $objects[$config->getProviderClass()];
        $object->attachProviderConfig($config);

        $key = $object->getProviderKey();
        if (isset($providers[$key])) {
          throw new Exception(
            pht(
              "Two authentication providers use the same provider key ".
              "('%s'). Each provider must be identified by a unique key.",
              $key));
        }
        $providers[$key] = $object;
      }
    }

    return $providers;
  }

  public static function getAllEnabledProviders() {
    $providers = self::getAllProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->isEnabled()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  public static function getEnabledProviderByKey($provider_key) {
    return idx(self::getAllEnabledProviders(), $provider_key);
  }

  abstract public function getProviderName();
  abstract public function getAdapter();

  public function isEnabled() {
    return $this->getProviderConfig()->getIsEnabled();
  }

  public function shouldAllowLogin() {
    return $this->getProviderConfig()->getShouldAllowLogin();
  }

  public function shouldAllowRegistration() {
    if (!$this->shouldAllowLogin()) {
      return false;
    }

    return $this->getProviderConfig()->getShouldAllowRegistration();
  }

  public function shouldAllowAccountLink() {
    return $this->getProviderConfig()->getShouldAllowLink();
  }

  public function shouldAllowAccountUnlink() {
    return $this->getProviderConfig()->getShouldAllowUnlink();
  }

  public function shouldTrustEmails() {
    return $this->shouldAllowEmailTrustConfiguration() &&
           $this->getProviderConfig()->getShouldTrustEmails();
  }

  /**
   * Should we allow the adapter to be marked as "trusted". This is true for
   * all adapters except those that allow the user to type in emails (see
   * @{class:PhabricatorPasswordAuthProvider}).
   */
  public function shouldAllowEmailTrustConfiguration() {
    return true;
  }

  public function buildLoginForm(PhabricatorAuthStartController $controller) {
    return $this->renderLoginForm($controller->getRequest(), $mode = 'start');
  }

  public function buildInviteForm(PhabricatorAuthStartController $controller) {
    return $this->renderLoginForm($controller->getRequest(), $mode = 'invite');
  }

  abstract public function processLoginRequest(
    PhabricatorAuthLoginController $controller);

  public function buildLinkForm(PhabricatorAuthLinkController $controller) {
    return $this->renderLoginForm($controller->getRequest(), $mode = 'link');
  }

  public function shouldAllowAccountRefresh() {
    return true;
  }

  public function buildRefreshForm(
    PhabricatorAuthLinkController $controller) {
    return $this->renderLoginForm($controller->getRequest(), $mode = 'refresh');
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    throw new PhutilMethodNotImplementedException();
  }

  public function createProviders() {
    return array($this);
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    return;
  }

  public function willRegisterAccount(PhabricatorExternalAccount $account) {
    return;
  }

  protected function loadOrCreateAccount($account_id) {
    if (!strlen($account_id)) {
      throw new Exception(pht('Empty account ID!'));
    }

    $adapter = $this->getAdapter();
    $adapter_class = get_class($adapter);

    if (!strlen($adapter->getAdapterType())) {
      throw new Exception(
        pht(
          "AuthAdapter (of class '%s') has an invalid implementation: ".
          "no adapter type.",
          $adapter_class));
    }

    if (!strlen($adapter->getAdapterDomain())) {
      throw new Exception(
        pht(
          "AuthAdapter (of class '%s') has an invalid implementation: ".
          "no adapter domain.",
          $adapter_class));
    }

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s',
      $adapter->getAdapterType(),
      $adapter->getAdapterDomain(),
      $account_id);
    if (!$account) {
      $account = id(new PhabricatorExternalAccount())
        ->setAccountType($adapter->getAdapterType())
        ->setAccountDomain($adapter->getAdapterDomain())
        ->setAccountID($account_id);
    }

    $account->setUsername($adapter->getAccountName());
    $account->setRealName($adapter->getAccountRealName());
    $account->setEmail($adapter->getAccountEmail());
    $account->setAccountURI($adapter->getAccountURI());

    $account->setProfileImagePHID(null);
    $image_uri = $adapter->getAccountImageURI();
    if ($image_uri) {
      try {
        $name = PhabricatorSlug::normalize($this->getProviderName());
        $name = $name.'-profile.jpg';

        // TODO: If the image has not changed, we do not need to make a new
        // file entry for it, but there's no convenient way to do this with
        // PhabricatorFile right now. The storage will get shared, so the impact
        // here is negligible.
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          $image_file = PhabricatorFile::newFromFileDownload(
            $image_uri,
            array(
              'name' => $name,
              'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
            ));
          if ($image_file->isViewableImage()) {
            $image_file
              ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
              ->setCanCDN(true)
              ->save();
            $account->setProfileImagePHID($image_file->getPHID());
          } else {
            $image_file->delete();
          }
        unset($unguarded);

      } catch (Exception $ex) {
        // Log this but proceed, it's not especially important that we
        // be able to pull profile images.
        phlog($ex);
      }
    }

    $this->willSaveAccount($account);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $account->save();
    unset($unguarded);

    return $account;
  }

  public function getLoginURI() {
    $app = PhabricatorApplication::getByClass('PhabricatorAuthApplication');
    return $app->getApplicationURI('/login/'.$this->getProviderKey().'/');
  }

  public function getSettingsURI() {
    return '/settings/panel/external/';
  }

  public function getStartURI() {
    $app = PhabricatorApplication::getByClass('PhabricatorAuthApplication');
    $uri = $app->getApplicationURI('/start/');
    return $uri;
  }

  public function isDefaultRegistrationProvider() {
    return false;
  }

  public function shouldRequireRegistrationPassword() {
    return false;
  }

  public function getDefaultExternalAccount() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getLoginOrder() {
    return '500-'.$this->getProviderName();
  }

  protected function getLoginIcon() {
    return 'Generic';
  }

  public function isLoginFormAButton() {
    return false;
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    return null;
  }

  public function readFormValuesFromProvider() {
    return array();
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    return array();
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

    return;
  }

  public function willRenderLinkedAccount(
    PhabricatorUser $viewer,
    PHUIObjectItemView $item,
    PhabricatorExternalAccount $account) {

    $account_view = id(new PhabricatorAuthAccountView())
      ->setExternalAccount($account)
      ->setAuthProvider($this);

    $item->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => 'mmr mml mst mmb',
        ),
        $account_view));
  }

  /**
   * Return true to use a two-step configuration (setup, configure) instead of
   * the default single-step configuration. In practice, this means that
   * creating a new provider instance will redirect back to the edit page
   * instead of the provider list.
   *
   * @return bool True if this provider uses two-step configuration.
   */
  public function hasSetupStep() {
    return false;
  }

  /**
   * Render a standard login/register button element.
   *
   * The `$attributes` parameter takes these keys:
   *
   *   - `uri`: URI the button should take the user to when clicked.
   *   - `method`: Optional HTTP method the button should use, defaults to GET.
   *
   * @param   AphrontRequest  HTTP request.
   * @param   string          Request mode string.
   * @param   map             Additional parameters, see above.
   * @return  wild            Login button.
   */
  protected function renderStandardLoginButton(
    AphrontRequest $request,
    $mode,
    array $attributes = array()) {

    PhutilTypeSpec::checkMap(
      $attributes,
      array(
        'method' => 'optional string',
        'uri' => 'string',
        'sigil' => 'optional string',
      ));

    $viewer = $request->getUser();
    $adapter = $this->getAdapter();

    if ($mode == 'link') {
      $button_text = pht('Link External Account');
    } else if ($mode == 'refresh') {
      $button_text = pht('Refresh Account Link');
    } else if ($mode == 'invite') {
      $button_text = pht('Register Account');
    } else if ($this->shouldAllowRegistration()) {
      $button_text = pht('Login or Register');
    } else {
      $button_text = pht('Login');
    }

    $icon = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
      ->setSpriteIcon($this->getLoginIcon());

    $button = id(new PHUIButtonView())
      ->setSize(PHUIButtonView::BIG)
      ->setColor(PHUIButtonView::GREY)
      ->setIcon($icon)
      ->setText($button_text)
      ->setSubtext($this->getProviderName());

    $uri = $attributes['uri'];
    $uri = new PhutilURI($uri);
    $params = $uri->getQueryParams();
    $uri->setQueryParams(array());

    $content = array($button);

    foreach ($params as $key => $value) {
      $content[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }

    return phabricator_form(
      $viewer,
      array(
        'method' => idx($attributes, 'method', 'GET'),
        'action' => (string)$uri,
        'sigil'  => idx($attributes, 'sigil'),
      ),
      $content);
  }

  public function renderConfigurationFooter() {
    return null;
  }

  public function getAuthCSRFCode(AphrontRequest $request) {
    $phcid = $request->getCookie(PhabricatorCookies::COOKIE_CLIENTID);
    if (!strlen($phcid)) {
      throw new AphrontMalformedRequestException(
        pht('Missing Client ID Cookie'),
        pht(
          'Your browser did not submit a "%s" cookie with client state '.
          'information in the request. Check that cookies are enabled. '.
          'If this problem persists, you may need to clear your cookies.',
          PhabricatorCookies::COOKIE_CLIENTID),
        true);
    }

    return PhabricatorHash::digest($phcid);
  }

  protected function verifyAuthCSRFCode(AphrontRequest $request, $actual) {
    $expect = $this->getAuthCSRFCode($request);

    if (!strlen($actual)) {
      throw new Exception(
        pht(
          'The authentication provider did not return a client state '.
          'parameter in its response, but one was expected. If this '.
          'problem persists, you may need to clear your cookies.'));
    }

    if (!phutil_hashes_are_identical($actual, $expect)) {
      throw new Exception(
        pht(
          'The authentication provider did not return the correct client '.
          'state parameter in its response. If this problem persists, you may '.
          'need to clear your cookies.'));
    }
  }

  public function supportsAutoLogin() {
    return false;
  }

  public function getAutoLoginURI(AphrontRequest $request) {
    throw new PhutilMethodNotImplementedException();
  }

}
