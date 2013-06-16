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
        return $this->renderError(
          pht(
            'This external account ("%s") is already linked to your '.
            'Phabricator account.'));
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
              'install. An administrator may have recently disabled it.'));
        }
      }
    }

    // This should be unreachable, but fail explicitly if we get here somehow.
    return new Aphront400Response();
  }

  private function processLoginUser(PhabricatorExternalAccount $account) {
    // TODO: Implement.
    return new Aphront404Response();
  }

  private function processRegisterUser(PhabricatorExternalAccount $account) {
    if ($account->getUserPHID()) {
      throw new Exception("Account is already registered.");
    }

    // Regenerate the registration secret key, set it on the external account,
    // set a cookie on the user's machine, and redirect them to registration.
    // See PhabricatorAuthRegisterController for discussion of the registration
    // key.

    $registration_key = Filesystem::readRandomCharacters(32);
    $account->setProperty('registrationKey', $registration_key);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $account->save();
    unset($unguarded);

    $this->getRequest()->setCookie('phreg', $registration_key);

    $account_secret = $account->getAccountSecret();
    $register_uri = $this->getApplicationURI('register/'.$account_secret.'/');
    return id(new AphrontRedirectResponse())->setURI($register_uri);
  }

  private function processLinkUser(PhabricatorExternalAccount $account) {
    // TODO: Implement.
    return new Aphront404Response();
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

  private function renderError($message) {
    $title = pht('Login Failed');

    $view = new AphrontErrorView();
    $view->setTitle($title);
    $view->appendChild($message);

    return $this->buildApplicationPage(
      $view,
      array(
        'title' => $title,
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
