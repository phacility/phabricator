<?php

final class PhabricatorOAuthLoginController
  extends PhabricatorAuthController {

  private $provider;
  private $userID;

  private $accessToken;
  private $oauthState;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {
    $current_user = $this->getRequest()->getUser();

    $provider = $this->provider;
    if (!$provider->isProviderEnabled()) {
      return new Aphront400Response();
    }

    $provider_name = $provider->getProviderName();
    $provider_key = $provider->getProviderKey();

    $request = $this->getRequest();

    if ($request->getStr('error')) {
      $error_view = id(new PhabricatorOAuthFailureView())
        ->setRequest($request);
      return $this->buildErrorResponse($error_view);
    }

    $error_response = $this->retrieveAccessToken($provider);
    if ($error_response) {
      return $error_response;
    }

    $userinfo_uri = new PhutilURI($provider->getUserInfoURI());
    $userinfo_uri->setQueryParam('access_token', $this->accessToken);
    $userinfo_uri = (string)$userinfo_uri;

    try {
      $user_data_request = new HTTPSFuture($userinfo_uri);

      // NOTE: GitHub requires a User-Agent header.
      $user_data_request->addHeader('User-Agent', 'Phabricator');

      list($body) = $user_data_request->resolvex();
      $provider->setUserData($body);
    } catch (PhabricatorOAuthProviderException $e) {
      return $this->buildErrorResponse(new PhabricatorOAuthFailureView(), $e);
    }
    $provider->setAccessToken($this->accessToken);

    $user_id = $provider->retrieveUserID();
    $provider_key = $provider->getProviderKey();

    $oauth_info = $this->retrieveOAuthInfo($provider);

    if ($current_user->getPHID()) {
      if ($oauth_info->getID()) {
        if ($oauth_info->getUserID() != $current_user->getID()) {
          $dialog = new AphrontDialogView();
          $dialog->setUser($current_user);
          $dialog->setTitle(pht('Already Linked to Another Account'));
          $dialog->appendChild(phutil_tag(
            'p',
            array(),
            pht(
              'The %s account you just authorized is already linked to '.
              'another Phabricator account. Before you can associate your %s '.
              'account with this Phabriactor account, you must unlink it from '.
              'the Phabricator account it is currently linked to.',
              $provider_name,
              $provider_name)));
          $dialog->addCancelButton($provider->getSettingsPanelURI());

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } else {
          $this->saveOAuthInfo($oauth_info); // Refresh token.
          return id(new AphrontRedirectResponse())
            ->setURI($provider->getSettingsPanelURI());
        }
      }

      $existing_oauth = PhabricatorUserOAuthInfo::loadOneByUserAndProviderKey(
        $current_user,
        $provider_key);

      if ($existing_oauth) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle(
          pht('Already Linked to an Account From This Provider'));
        $dialog->appendChild(phutil_tag(
          'p',
          array(),
          pht(
            'The account you are logged in with is already linked to a %s '.
            'account. Before you can link it to a different %s account, you '.
            'must unlink the old account.',
            $provider_name,
            $provider_name)));
        $dialog->addCancelButton($provider->getSettingsPanelURI());
        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle(pht('Link %s Account', $provider_name));
        $dialog->appendChild(phutil_tag('p', array(), pht(
          'Link your %s account to your Phabricator account?',
          $provider_name)));
        $dialog->addHiddenInput('confirm_token', $provider->getAccessToken());
        $dialog->addHiddenInput('state', $this->oauthState);
        $dialog->addSubmitButton('Link Accounts');
        $dialog->addCancelButton($provider->getSettingsPanelURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      $oauth_info->setUserID($current_user->getID());

      $this->saveOAuthInfo($oauth_info);

      return id(new AphrontRedirectResponse())
        ->setURI($provider->getSettingsPanelURI());
    }

    // Login with known auth.

    if ($oauth_info->getID()) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $known_user = id(new PhabricatorUser())->load($oauth_info->getUserID());

      $request->getApplicationConfiguration()->willAuthenticateUserWithOAuth(
        $known_user,
        $oauth_info,
        $provider);

      $this->saveOAuthInfo($oauth_info);

      return $this->loginUser($known_user);
    }

    $oauth_email = $provider->retrieveUserEmail();
    if ($oauth_email) {
      $known_email = id(new PhabricatorUserEmail())
        ->loadOneWhere('address = %s', $oauth_email);
      if ($known_email) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle(pht('Already Linked to Another Account'));
        $dialog->appendChild(phutil_tag(
          'p',
          array(),
          pht(
            'The %s account you just authorized has an email address which '.
            'is already in use by another Phabricator account. To link the '.
            'accounts, log in to your Phabricator account and then go to '.
            'Settings.',
            $provider_name)));

        $dialog->addCancelButton('/login/');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    if (!$provider->isProviderRegistrationEnabled()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($current_user);
      $dialog->setTitle(pht('No Account Registration with %s', $provider_name));
      $dialog->appendChild(phutil_tag(
        'p',
        array(),
        pht(
          'You can not register a new account using %s; you can only use '.
          'your %s account to log into an existing Phabricator account which '.
          'you have registered through other means.',
          $provider_name,
          $provider_name)));
      $dialog->addCancelButton('/login/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $controller = PhabricatorEnv::newObjectFromConfig(
      'controller.oauth-registration',
      array($this->getRequest()));

    $controller->setOAuthProvider($provider);
    $controller->setOAuthInfo($oauth_info);
    $controller->setOAuthState($this->oauthState);

    return $this->delegateToController($controller);
  }

  private function buildErrorResponse(PhabricatorOAuthFailureView $view,
    Exception $e = null) {

    $provider = $this->provider;

    $provider_name = $provider->getProviderName();
    $view->setOAuthProvider($provider);

    if ($e) {
      $view->setException($e);
    }

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => pht('Auth Failed'),
      ));
  }

  private function retrieveAccessToken(PhabricatorOAuthProvider $provider) {
    $request = $this->getRequest();

    $token = $request->getStr('confirm_token');
    if ($token) {
      $this->accessToken  = $token;
      $this->oauthState   = $request->getStr('state');
      return null;
    }

    $client_id      = $provider->getClientID();
    $client_secret  = $provider->getClientSecret();
    $redirect_uri   = $provider->getRedirectURI();
    $auth_uri       = $provider->getTokenURI();

    $code = $request->getStr('code');
    $query_data = array(
      'client_id'     => $client_id,
      'client_secret' => $client_secret,
      'redirect_uri'  => $redirect_uri,
      'code'          => $code,
    ) + $provider->getExtraTokenParameters();

    $future = new HTTPSFuture($auth_uri, $query_data);
    $future->setMethod('POST');
    try {
      list($response) = $future->resolvex();
    } catch (Exception $ex) {
      return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
    }
    $data = $provider->decodeTokenResponse($response);

    $token = idx($data, 'access_token');
    if (!$token) {
      return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
    }

    $this->accessToken  = $token;
    $this->oauthState   = $request->getStr('state');

    return null;
  }

  private function retrieveOAuthInfo(PhabricatorOAuthProvider $provider) {

    $oauth_info = PhabricatorUserOAuthInfo::loadOneByProviderKeyAndAccountID(
      $provider->getProviderKey(),
      $provider->retrieveUserID());

    if (!$oauth_info) {
      $oauth_info = new PhabricatorUserOAuthInfo(
        new PhabricatorExternalAccount());
      $oauth_info->setOAuthProvider($provider->getProviderKey());
      $oauth_info->setOAuthUID($provider->retrieveUserID());
    }

    $oauth_info->setAccountURI($provider->retrieveUserAccountURI());
    $oauth_info->setAccountName($provider->retrieveUserAccountName());
    $oauth_info->setToken($provider->getAccessToken());

    return $oauth_info;
  }

  private function saveOAuthInfo(PhabricatorUserOAuthInfo $info) {
    // UNGUARDED WRITES: Logging-in users don't have their CSRF set up yet.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $info->save();
  }
}
