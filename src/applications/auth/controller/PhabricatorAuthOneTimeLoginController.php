<?php

final class PhabricatorAuthOneTimeLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $link_type = $request->getURIData('type');
    $key = $request->getURIData('key');
    $email_id = $request->getURIData('emailID');

    $target_user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($id))
      ->executeOne();
    if (!$target_user) {
      return new Aphront404Response();
    }

    // NOTE: We allow you to use a one-time login link for your own current
    // login account. This supports the "Set Password" flow.

    $is_logged_in = false;
    if ($viewer->isLoggedIn()) {
      if ($viewer->getPHID() !== $target_user->getPHID()) {
        return $this->renderError(
          pht('You are already logged in.'));
      } else {
        $is_logged_in = true;
      }
    }

    // NOTE: As a convenience to users, these one-time login URIs may also
    // be associated with an email address which will be verified when the
    // URI is used.

    // This improves the new user experience for users receiving "Welcome"
    // emails on installs that require verification: if we did not verify the
    // email, they'd immediately get roadblocked with a "Verify Your Email"
    // error and have to go back to their email account, wait for a
    // "Verification" email, and then click that link to actually get access to
    // their account. This is hugely unwieldy, and if the link was only sent
    // to the user's email in the first place we can safely verify it as a
    // side effect of login.

    // The email hashed into the URI so users can't verify some email they
    // do not own by doing this:
    //
    //  - Add some address you do not own;
    //  - request a password reset;
    //  - change the URI in the email to the address you don't own;
    //  - login via the email link; and
    //  - get a "verified" address you don't control.

    $target_email = null;
    if ($email_id) {
      $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
        'userPHID = %s AND id = %d',
        $target_user->getPHID(),
        $email_id);
      if (!$target_email) {
        return new Aphront404Response();
      }
    }

    $engine = new PhabricatorAuthSessionEngine();
    $token = $engine->loadOneTimeLoginKey(
      $target_user,
      $target_email,
      $key);

    if (!$token) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Log In'))
        ->setShortTitle(pht('Login Failure'))
        ->appendParagraph(
          pht(
            'The login link you clicked is invalid, out of date, or has '.
            'already been used.'))
        ->appendParagraph(
          pht(
            'Make sure you are copy-and-pasting the entire link into '.
            'your browser. Login links are only valid for 24 hours, and '.
            'can only be used once.'))
        ->appendParagraph(
          pht('You can try again, or request a new link via email.'))
        ->addCancelButton('/login/email/', pht('Send Another Email'));
    }

    if (!$target_user->canEstablishWebSessions()) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Establish Web Session'))
        ->setShortTitle(pht('Login Failure'))
        ->appendParagraph(
          pht(
            'You are trying to gain access to an account ("%s") that can not '.
            'establish a web session.',
            $target_user->getUsername()))
        ->appendParagraph(
          pht(
            'Special users like daemons and mailing lists are not permitted '.
            'to log in via the web. Log in as a normal user instead.'))
        ->addCancelButton('/');
    }

    if ($request->isFormPost() || $is_logged_in) {
      // If we have an email bound into this URI, verify email so that clicking
      // the link in the "Welcome" email is good enough, without requiring users
      // to go through a second round of email verification.

      $editor = id(new PhabricatorUserEditor())
        ->setActor($target_user);

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        // Nuke the token and all other outstanding password reset tokens.
        // There is no particular security benefit to destroying them all, but
        // it should reduce HackerOne reports of nebulous harm.
        $editor->revokePasswordResetLinks($target_user);

        if ($target_email) {
          $editor->verifyEmail($target_user, $target_email);
        }
      unset($unguarded);

      $next_uri = $this->getNextStepURI($target_user);

      // If the user is already logged in, we're just doing a "password set"
      // flow. Skip directly to the next step.
      if ($is_logged_in) {
        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }

      PhabricatorCookies::setNextURICookie($request, $next_uri, $force = true);

      $force_full_session = false;
      if ($link_type === PhabricatorAuthSessionEngine::ONETIME_RECOVER) {
        $force_full_session = $token->getShouldForceFullSession();
      }

      return $this->loginUser($target_user, $force_full_session);
    }

    // NOTE: We need to CSRF here so attackers can't generate an email link,
    // then log a user in to an account they control via sneaky invisible
    // form submissions.

    switch ($link_type) {
      case PhabricatorAuthSessionEngine::ONETIME_WELCOME:
        $title = pht(
          'Welcome to %s',
          PlatformSymbols::getPlatformServerName());
        break;
      case PhabricatorAuthSessionEngine::ONETIME_RECOVER:
        $title = pht('Account Recovery');
        break;
      case PhabricatorAuthSessionEngine::ONETIME_USERNAME:
      case PhabricatorAuthSessionEngine::ONETIME_RESET:
      default:
        $title = pht(
          'Log in to %s',
          PlatformSymbols::getPlatformServerName());
        break;
    }

    $body = array();
    $body[] = pht(
      'Use the button below to log in as: %s',
      phutil_tag('strong', array(), $target_user->getUsername()));

    if ($target_email && !$target_email->getIsVerified()) {
      $body[] = pht(
        'Logging in will verify %s as an email address you own.',
        phutil_tag('strong', array(), $target_email->getAddress()));

    }

    $body[] = pht(
      'After logging in you should set a password for your account, or '.
      'link your account to an external account that you can use to '.
      'authenticate in the future.');

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addSubmitButton(pht('Log In (%s)', $target_user->getUsername()))
      ->addCancelButton('/');

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function getNextStepURI(PhabricatorUser $user) {
    $request = $this->getRequest();

    // If we have password auth, let the user set or reset their password after
    // login.
    $have_passwords = PhabricatorPasswordAuthProvider::getPasswordProvider();
    if ($have_passwords) {
      // We're going to let the user reset their password without knowing
      // the old one. Generate a one-time token for that.
      $key = Filesystem::readRandomCharacters(16);
      $password_type =
        PhabricatorAuthPasswordResetTemporaryTokenType::TOKENTYPE;

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        id(new PhabricatorAuthTemporaryToken())
          ->setTokenResource($user->getPHID())
          ->setTokenType($password_type)
          ->setTokenExpires(time() + phutil_units('1 hour in seconds'))
          ->setTokenCode(PhabricatorHash::weakDigest($key))
          ->save();
      unset($unguarded);

      $panel_uri = '/auth/password/';

      $request->setTemporaryCookie(PhabricatorCookies::COOKIE_HISEC, 'yes');

      $params = array(
        'key' => $key,
      );

      return (string)new PhutilURI($panel_uri, $params);
    }

    // Check if the user already has external accounts linked. If they do,
    // it's not obvious why they aren't using them to log in, but assume they
    // know what they're doing. We won't send them to the link workflow.
    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->execute();

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($user)
      ->withIsEnabled(true)
      ->execute();

    $linkable = array();
    foreach ($configs as $config) {
      if (!$config->getShouldAllowLink()) {
        continue;
      }

      $provider = $config->getProvider();
      if (!$provider->isLoginFormAButton()) {
        continue;
      }

      $linkable[] = $provider;
    }

    // If there's at least one linkable provider, and the user doesn't already
    // have accounts, send the user to the link workflow.
    if (!$accounts && $linkable) {
      return '/auth/external/';
    }

    // If there are no configured providers and the user is an administrator,
    // send them to Auth to configure a provider. This is probably where they
    // want to go. You can end up in this state by accidentally losing your
    // first session during initial setup, or after restoring exported data
    // from a hosted instance.
    if (!$configs && $user->getIsAdmin()) {
      return '/auth/';
    }

    // If we didn't find anywhere better to send them, give up and just send
    // them to the home page.
    return '/';
  }

}
