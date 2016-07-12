<?php

abstract class PhabricatorAuthController extends PhabricatorController {

  protected function renderErrorPage($title, array $messages) {
    $view = new PHUIInfoView();
    $view->setTitle($title);
    $view->setErrors($messages);

    return $this->newPage()
      ->setTitle($title)
      ->appendChild($view);

  }

  /**
   * Returns true if this install is newly setup (i.e., there are no user
   * accounts yet). In this case, we enter a special mode to permit creation
   * of the first account form the web UI.
   */
  protected function isFirstTimeSetup() {
    // If there are any auth providers, this isn't first time setup, even if
    // we don't have accounts.
    if (PhabricatorAuthProvider::getAllEnabledProviders()) {
      return false;
    }

    // Otherwise, check if there are any user accounts. If not, we're in first
    // time setup.
    $any_users = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->setLimit(1)
      ->execute();

    return !$any_users;
  }


  /**
   * Log a user into a web session and return an @{class:AphrontResponse} which
   * corresponds to continuing the login process.
   *
   * Normally, this is a redirect to the validation controller which makes sure
   * the user's cookies are set. However, event listeners can intercept this
   * event and do something else if they prefer.
   *
   * @param   PhabricatorUser   User to log the viewer in as.
   * @return  AphrontResponse   Response which continues the login process.
   */
  protected function loginUser(PhabricatorUser $user) {

    $response = $this->buildLoginValidateResponse($user);
    $session_type = PhabricatorAuthSession::TYPE_WEB;

    $event_type = PhabricatorEventType::TYPE_AUTH_WILLLOGINUSER;
    $event_data = array(
      'user'        => $user,
      'type'        => $session_type,
      'response'    => $response,
      'shouldLogin' => true,
    );

    $event = id(new PhabricatorEvent($event_type, $event_data))
      ->setUser($user);
    PhutilEventEngine::dispatchEvent($event);

    $should_login = $event->getValue('shouldLogin');
    if ($should_login) {
      $session_key = id(new PhabricatorAuthSessionEngine())
        ->establishSession($session_type, $user->getPHID(), $partial = true);

      // NOTE: We allow disabled users to login and roadblock them later, so
      // there's no check for users being disabled here.

      $request = $this->getRequest();
      $request->setCookie(
        PhabricatorCookies::COOKIE_USERNAME,
        $user->getUsername());
      $request->setCookie(
        PhabricatorCookies::COOKIE_SESSION,
        $session_key);

      $this->clearRegistrationCookies();
    }

    return $event->getValue('response');
  }

  protected function clearRegistrationCookies() {
    $request = $this->getRequest();

    // Clear the registration key.
    $request->clearCookie(PhabricatorCookies::COOKIE_REGISTRATION);

    // Clear the client ID / OAuth state key.
    $request->clearCookie(PhabricatorCookies::COOKIE_CLIENTID);

    // Clear the invite cookie.
    $request->clearCookie(PhabricatorCookies::COOKIE_INVITE);
  }

  private function buildLoginValidateResponse(PhabricatorUser $user) {
    $validate_uri = new PhutilURI($this->getApplicationURI('validate/'));
    $validate_uri->setQueryParam('expect', $user->getUsername());

    return id(new AphrontRedirectResponse())->setURI((string)$validate_uri);
  }

  protected function renderError($message) {
    return $this->renderErrorPage(
      pht('Authentication Error'),
      array(
        $message,
      ));
  }

  protected function loadAccountForRegistrationOrLinking($account_key) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $account = null;
    $provider = null;
    $response = null;

    if (!$account_key) {
      $response = $this->renderError(
        pht('Request did not include account key.'));
      return array($account, $provider, $response);
    }

    // NOTE: We're using the omnipotent user because the actual user may not
    // be logged in yet, and because we want to tailor an error message to
    // distinguish between "not usable" and "does not exist". We do explicit
    // checks later on to make sure this account is valid for the intended
    // operation. This requires edit permission for completeness and consistency
    // but it won't actually be meaningfully checked because we're using the
    // ominpotent user.

    $account = id(new PhabricatorExternalAccountQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withAccountSecrets(array($account_key))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$account) {
      $response = $this->renderError(pht('No valid linkable account.'));
      return array($account, $provider, $response);
    }

    if ($account->getUserPHID()) {
      if ($account->getUserPHID() != $viewer->getPHID()) {
        $response = $this->renderError(
          pht(
            'The account you are attempting to register or link is already '.
            'linked to another user.'));
      } else {
        $response = $this->renderError(
          pht(
            'The account you are attempting to link is already linked '.
            'to your account.'));
      }
      return array($account, $provider, $response);
    }

    $registration_key = $request->getCookie(
      PhabricatorCookies::COOKIE_REGISTRATION);

    // NOTE: This registration key check is not strictly necessary, because
    // we're only creating new accounts, not linking existing accounts. It
    // might be more hassle than it is worth, especially for email.
    //
    // The attack this prevents is getting to the registration screen, then
    // copy/pasting the URL and getting someone else to click it and complete
    // the process. They end up with an account bound to credentials you
    // control. This doesn't really let you do anything meaningful, though,
    // since you could have simply completed the process yourself.

    if (!$registration_key) {
      $response = $this->renderError(
        pht(
          'Your browser did not submit a registration key with the request. '.
          'You must use the same browser to begin and complete registration. '.
          'Check that cookies are enabled and try again.'));
      return array($account, $provider, $response);
    }

    // We store the digest of the key rather than the key itself to prevent a
    // theoretical attacker with read-only access to the database from
    // hijacking registration sessions.

    $actual = $account->getProperty('registrationKey');
    $expect = PhabricatorHash::digest($registration_key);
    if (!phutil_hashes_are_identical($actual, $expect)) {
      $response = $this->renderError(
        pht(
          'Your browser submitted a different registration key than the one '.
          'associated with this account. You may need to clear your cookies.'));
      return array($account, $provider, $response);
    }

    $other_account = id(new PhabricatorExternalAccount())->loadAllWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s
        AND id != %d',
      $account->getAccountType(),
      $account->getAccountDomain(),
      $account->getAccountID(),
      $account->getID());

    if ($other_account) {
      $response = $this->renderError(
        pht(
          'The account you are attempting to register with already belongs '.
          'to another user.'));
      return array($account, $provider, $response);
    }

    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $account->getProviderKey());

    if (!$provider) {
      $response = $this->renderError(
        pht(
          'The account you are attempting to register with uses a nonexistent '.
          'or disabled authentication provider (with key "%s"). An '.
          'administrator may have recently disabled this provider.',
          $account->getProviderKey()));
      return array($account, $provider, $response);
    }

    return array($account, $provider, null);
  }

  protected function loadInvite() {
    $invite_cookie = PhabricatorCookies::COOKIE_INVITE;
    $invite_code = $this->getRequest()->getCookie($invite_cookie);
    if (!$invite_code) {
      return null;
    }

    $engine = id(new PhabricatorAuthInviteEngine())
      ->setViewer($this->getViewer())
      ->setUserHasConfirmedVerify(true);

    try {
      return $engine->processInviteCode($invite_code);
    } catch (Exception $ex) {
      // If this fails for any reason, just drop the invite. In normal
      // circumstances, we gave them a detailed explanation of any error
      // before they jumped into this workflow.
      return null;
    }
  }

  protected function renderInviteHeader(PhabricatorAuthInvite $invite) {
    $viewer = $this->getViewer();

    $invite_author = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($invite->getAuthorPHID()))
      ->needProfileImage(true)
      ->executeOne();

    // If we can't load the author for some reason, just drop this message.
    // We lose the value of contextualizing things without author details.
    if (!$invite_author) {
      return null;
    }

    $invite_item = id(new PHUIObjectItemView())
      ->setHeader(pht('Welcome to Phabricator!'))
      ->setImageURI($invite_author->getProfileImageURI())
      ->addAttribute(
        pht(
          '%s has invited you to join Phabricator.',
          $invite_author->getFullName()));

    $invite_list = id(new PHUIObjectItemListView())
      ->addItem($invite_item)
      ->setFlush(true);

    return id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($invite_list);
  }

}
