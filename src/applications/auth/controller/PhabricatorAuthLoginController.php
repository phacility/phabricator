<?php

final class PhabricatorAuthLoginController
  extends PhabricatorAuthController {

  private $providerKey;
  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->providerKey = $data['pkey'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $response = $this->loadProvider();
    if ($response) {
      return $response;
    }

    $provider = $this->provider;

    list($account, $response) = $provider->processLoginRequest($this);
    if ($response) {
      return $response;
    }

    if (!$account) {
      throw new Exception(
        "Auth provider failed to load an account from processLoginRequest()!");
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
            'The external account ("%s") you just used to login is alerady '.
            'associated with another Phabricator user account. Login to the '.
            'other Phabricator account and unlink the external account before '.
            'linking it to a new Phabricator account.',
            $provider->getProviderName()));
      }
    } else {
      // The account is not yet attached to a Phabricator user, so this is
      // either a registration or an account link request.
      if (!$viewer->isLoggedIn()) {
        if ($provider->shouldAllowRegistration()) {
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
      throw new Exception("Account is already registered or linked.");
    }

    // Regenerate the registration secret key, set it on the external account,
    // set a cookie on the user's machine, and redirect them to registration.
    // See PhabricatorAuthRegisterController for discussion of the registration
    // key.

    $registration_key = Filesystem::readRandomCharacters(32);
    $account->setProperty(
      'registrationKey',
      PhabricatorHash::digest($registration_key));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $account->save();
    unset($unguarded);

    $this->getRequest()->setCookie('phreg', $registration_key);

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

  private function loadProvider() {
    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $this->providerKey);

    if (!$provider) {
      return $this->renderError(
        pht(
          'The account you are attempting to login with uses a nonexistent '.
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

    if ($this->getRequest()->getUser()->isLoggedIn()) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Link Account'))
          ->setHref($provider->getSettingsURI()));
    } else {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Login'))
          ->setHref($this->getApplicationURI('start/')));
    }

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($provider->getProviderName()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => pht('Login'),
        'device' => true,
        'dust' => true,
      ));
  }

  public function buildProviderErrorResponse(
    PhabricatorAuthProvider $provider,
    $message) {

    $message = pht(
      'Authentication provider ("%s") encountered an error during login. %s',
      $provider->getProviderName(),
      $message);

    return $this->renderError($message);
  }

}
