<?php

abstract class PhabricatorOAuth2AuthProvider
  extends PhabricatorOAuthAuthProvider {

  const PROPERTY_APP_ID = 'oauth:app:id';
  const PROPERTY_APP_SECRET = 'oauth:app:secret';

  protected function getIDKey() {
    return self::PROPERTY_APP_ID;
  }

  protected function getSecretKey() {
    return self::PROPERTY_APP_SECRET;
  }


  protected function configureAdapter(PhutilOAuthAuthAdapter $adapter) {
    $config = $this->getProviderConfig();
    $adapter->setClientID($config->getProperty(self::PROPERTY_APP_ID));
    $adapter->setClientSecret(
      new PhutilOpaqueEnvelope(
        $config->getProperty(self::PROPERTY_APP_SECRET)));
    $adapter->setRedirectURI(PhabricatorEnv::getURI($this->getLoginURI()));
    return $adapter;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $adapter = $this->getAdapter();
    $adapter->setState($this->getAuthCSRFCode($request));

    $scope = $request->getStr('scope');
    if ($scope) {
      $adapter->setScope($scope);
    }

    $attributes = array(
      'method' => 'GET',
      'uri' => $adapter->getAuthenticateURI(),
    );

    return $this->renderStandardLoginButton($request, $mode, $attributes);
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();
    $account = null;
    $response = null;

    $error = $request->getStr('error');
    if ($error) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider returned an error: %s',
          $error));

      return array($account, $response);
    }

    $this->verifyAuthCSRFCode($request, $request->getStr('state'));

    $code = $request->getStr('code');
    if (!strlen($code)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider did not return a "code" parameter in its '.
          'response.'));

      return array($account, $response);
    }

    $adapter->setCode($code);

    // NOTE: As a side effect, this will cause the OAuth adapter to request
    // an access token.

    try {
      $account_id = $adapter->getAccountID();
    } catch (Exception $ex) {
      // TODO: Handle this in a more user-friendly way.
      throw $ex;
    }

    if (!strlen($account_id)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider failed to retrieve an account ID.'));

      return array($account, $response);
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    return $this->processOAuthEditForm(
      $request,
      $values,
      pht('Application ID is required.'),
      pht('Application secret is required.'));
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    return $this->extendOAuthEditForm(
      $request,
      $form,
      $values,
      $issues,
      pht('OAuth App ID'),
      pht('OAuth App Secret'));
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    switch ($key) {
      case self::PROPERTY_APP_ID:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application ID for this provider from '.
            '"%s" to "%s".',
            $xaction->renderHandleLink($author_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s set the OAuth application ID for this provider to '.
            '"%s".',
            $xaction->renderHandleLink($author_phid),
            $new);
        }
      case self::PROPERTY_APP_SECRET:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application secret for this provider.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s set the OAuth application secret for this provider.',
            $xaction->renderHandleLink($author_phid));
        }
      case self::PROPERTY_NOTE:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application notes for this provider.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s set the OAuth application notes for this provider.',
            $xaction->renderHandleLink($author_phid));
        }

    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  protected function synchronizeOAuthAccount(
    PhabricatorExternalAccount $account) {
    $adapter = $this->getAdapter();

    $oauth_token = $adapter->getAccessToken();
    $account->setProperty('oauth.token.access', $oauth_token);

    if ($adapter->supportsTokenRefresh()) {
      $refresh_token = $adapter->getRefreshToken();
      $account->setProperty('oauth.token.refresh', $refresh_token);
    } else {
      $account->setProperty('oauth.token.refresh', null);
    }

    $expires = $adapter->getAccessTokenExpires();
    $account->setProperty('oauth.token.access.expires', $expires);
  }

  public function getOAuthAccessToken(
    PhabricatorExternalAccount $account,
    $force_refresh = false) {

    if ($account->getProviderKey() !== $this->getProviderKey()) {
      throw new Exception(pht('Account does not match provider!'));
    }

    if (!$force_refresh) {
      $access_expires = $account->getProperty('oauth.token.access.expires');
      $access_token = $account->getProperty('oauth.token.access');

      // Don't return a token with fewer than this many seconds remaining until
      // it expires.
      $shortest_token = 60;
      if ($access_token) {
        if ($access_expires === null ||
            $access_expires > (time() + $shortest_token)) {
          return $access_token;
        }
      }
    }

    $refresh_token = $account->getProperty('oauth.token.refresh');
    if ($refresh_token) {
      $adapter = $this->getAdapter();
      if ($adapter->supportsTokenRefresh()) {
        $adapter->refreshAccessToken($refresh_token);

        $this->synchronizeOAuthAccount($account);
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          $account->save();
        unset($unguarded);

        return $account->getProperty('oauth.token.access');
      }
    }

    return null;
  }

  public function willRenderLinkedAccount(
    PhabricatorUser $viewer,
    PHUIObjectItemView $item,
    PhabricatorExternalAccount $account) {

    // Get a valid token, possibly refreshing it. If we're unable to refresh
    // it, render a message to that effect. The user may be able to repair the
    // link by manually reconnecting.

    $is_invalid = false;
    try {
      $oauth_token = $this->getOAuthAccessToken($account);
    } catch (Exception $ex) {
      $oauth_token = null;
      $is_invalid = true;
    }

    $item->addAttribute(pht('OAuth2 Account'));

    if ($oauth_token) {
      $oauth_expires = $account->getProperty('oauth.token.access.expires');
      if ($oauth_expires) {
        $item->addAttribute(
          pht(
            'Active OAuth Token (Expires: %s)',
            phabricator_datetime($oauth_expires, $viewer)));
      } else {
        $item->addAttribute(
          pht('Active OAuth Token'));
      }
    } else if ($is_invalid) {
      $item->addAttribute(pht('Invalid OAuth Access Token'));
    } else {
      $item->addAttribute(pht('No OAuth Access Token'));
    }

    parent::willRenderLinkedAccount($viewer, $item, $account);
  }

  public function supportsAutoLogin() {
    return true;
  }

  public function getAutoLoginURI(AphrontRequest $request) {
    $csrf_code = $this->getAuthCSRFCode($request);

    $adapter = $this->getAdapter();
    $adapter->setState($csrf_code);

    return $adapter->getAuthenticateURI();
  }

}
