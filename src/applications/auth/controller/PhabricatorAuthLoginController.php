<?php

final class PhabricatorAuthLoginController
  extends PhabricatorAuthController {

  private $providerKey;
  private $extraURIData;
  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowRestrictedParameter($parameter_name) {
    // Whitelist the OAuth 'code' parameter.

    if ($parameter_name == 'code') {
      return true;
    }

    return parent::shouldAllowRestrictedParameter($parameter_name);
  }

  public function getExtraURIData() {
    return $this->extraURIData;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $this->providerKey = $request->getURIData('pkey');
    $this->extraURIData = $request->getURIData('extra');

    $response = $this->loadProvider();
    if ($response) {
      return $response;
    }

    $invite = $this->loadInvite();
    $provider = $this->provider;

    try {
      list($account, $response) = $provider->processLoginRequest($this);
    } catch (PhutilAuthUserAbortedException $ex) {
      if ($viewer->isLoggedIn()) {
        // If a logged-in user cancels, take them back to the external accounts
        // panel.
        $next_uri = '/settings/panel/external/';
      } else {
        // If a logged-out user cancels, take them back to the auth start page.
        $next_uri = '/';
      }

      // User explicitly hit "Cancel".
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Authentication Canceled'))
        ->appendChild(
          pht('You canceled authentication.'))
        ->addCancelButton($next_uri, pht('Continue'));
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($response) {
      return $response;
    }

    if (!$account) {
      throw new Exception(
        pht(
          'Auth provider failed to load an account from %s!',
          'processLoginRequest()'));
    }

    if ($account->getUserPHID()) {
      // The account is already attached to a Phabricator user, so this is
      // either a login or a bad account link request.
      if (!$viewer->isLoggedIn()) {
        if ($provider->shouldAllowLogin()) {
          return $this->processLoginUser($account);
        } else {
          return $this->renderError(
            pht(
              'The external account ("%s") you just authenticated with is '.
              'not configured to allow logins on this Phabricator install. '.
              'An administrator may have recently disabled it.',
              $provider->getProviderName()));
        }
      } else if ($viewer->getPHID() == $account->getUserPHID()) {
        // This is either an attempt to re-link an existing and already
        // linked account (which is silly) or a refresh of an external account
        // (e.g., an OAuth account).
        return id(new AphrontRedirectResponse())
          ->setURI('/settings/panel/external/');
      } else {
        return $this->renderError(
          pht(
            'The external account ("%s") you just used to log in is already '.
            'associated with another Phabricator user account. Log in to the '.
            'other Phabricator account and unlink the external account before '.
            'linking it to a new Phabricator account.',
            $provider->getProviderName()));
      }
    } else {
      // The account is not yet attached to a Phabricator user, so this is
      // either a registration or an account link request.
      if (!$viewer->isLoggedIn()) {
        if ($provider->shouldAllowRegistration() || $invite) {
          return $this->processRegisterUser($account);
        } else {
          return $this->renderError(
            pht(
              'The external account ("%s") you just authenticated with is '.
              'not configured to allow registration on this Phabricator '.
              'install. An administrator may have recently disabled it.',
              $provider->getProviderName()));
        }
      } else {

        // If the user already has a linked account on this provider, prevent
        // them from linking a second account. This can happen if they swap
        // logins and then refresh the account link.

        // There's no technical reason we can't allow you to link multiple
        // accounts from a single provider; disallowing this is currently a
        // product deciison. See T2549.

        $existing_accounts = id(new PhabricatorExternalAccountQuery())
          ->setViewer($viewer)
          ->withUserPHIDs(array($viewer->getPHID()))
          ->withProviderConfigPHIDs(
            array(
              $provider->getProviderConfigPHID(),
            ))
          ->execute();
        if ($existing_accounts) {
          return $this->renderError(
            pht(
              'Your Phabricator account is already connected to an external '.
              'account on this provider ("%s"), but you are currently logged '.
              'in to the provider with a different account. Log out of the '.
              'external service, then log back in with the correct account '.
              'before refreshing the account link.',
              $provider->getProviderName()));
        }

        if ($provider->shouldAllowAccountLink()) {
          return $this->processLinkUser($account);
        } else {
          return $this->renderError(
            pht(
              'The external account ("%s") you just authenticated with is '.
              'not configured to allow account linking on this Phabricator '.
              'install. An administrator may have recently disabled it.',
              $provider->getProviderName()));
        }
      }
    }

    // This should be unreachable, but fail explicitly if we get here somehow.
    return new Aphront400Response();
  }

  private function processLoginUser(PhabricatorExternalAccount $account) {
    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $account->getUserPHID());

    if (!$user) {
      return $this->renderError(
        pht(
          'The external account you just logged in with is not associated '.
          'with a valid Phabricator user.'));
    }

    return $this->loginUser($user);
  }

  private function processRegisterUser(PhabricatorExternalAccount $account) {
    $account_secret = $account->getAccountSecret();
    $register_uri = $this->getApplicationURI('register/'.$account_secret.'/');
    return $this->setAccountKeyAndContinue($account, $register_uri);
  }

  private function processLinkUser(PhabricatorExternalAccount $account) {
    $account_secret = $account->getAccountSecret();
    $confirm_uri = $this->getApplicationURI('confirmlink/'.$account_secret.'/');
    return $this->setAccountKeyAndContinue($account, $confirm_uri);
  }

  private function setAccountKeyAndContinue(
    PhabricatorExternalAccount $account,
    $next_uri) {

    if ($account->getUserPHID()) {
      throw new Exception(pht('Account is already registered or linked.'));
    }

    // Regenerate the registration secret key, set it on the external account,
    // set a cookie on the user's machine, and redirect them to registration.
    // See PhabricatorAuthRegisterController for discussion of the registration
    // key.

    $registration_key = Filesystem::readRandomCharacters(32);
    $account->setProperty(
      'registrationKey',
      PhabricatorHash::weakDigest($registration_key));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $account->save();
    unset($unguarded);

    $this->getRequest()->setTemporaryCookie(
      PhabricatorCookies::COOKIE_REGISTRATION,
      $registration_key);

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

  private function loadProvider() {
    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $this->providerKey);

    if (!$provider) {
      return $this->renderError(
        pht(
          'The account you are attempting to log in with uses a nonexistent '.
          'or disabled authentication provider (with key "%s"). An '.
          'administrator may have recently disabled this provider.',
          $this->providerKey));
    }

    $this->provider = $provider;

    return null;
  }

  protected function renderError($message) {
    return $this->renderErrorPage(
      pht('Login Failed'),
      array($message));
  }

  public function buildProviderPageResponse(
    PhabricatorAuthProvider $provider,
    $content) {

    $crumbs = $this->buildApplicationCrumbs();
    $viewer = $this->getViewer();

    if ($viewer->isLoggedIn()) {
      $crumbs->addTextCrumb(pht('Link Account'), $provider->getSettingsURI());
    } else {
      $crumbs->addTextCrumb(pht('Login'), $this->getApplicationURI('start/'));

      $content = array(
        $this->newCustomStartMessage(),
        $content,
      );
    }

    $crumbs->addTextCrumb($provider->getProviderName());
    $crumbs->setBorder(true);

    return $this->newPage()
      ->setTitle(pht('Login'))
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  public function buildProviderErrorResponse(
    PhabricatorAuthProvider $provider,
    $message) {

    $message = pht(
      'Authentication provider ("%s") encountered an error while attempting '.
      'to log in. %s', $provider->getProviderName(), $message);

    return $this->renderError($message);
  }

}
