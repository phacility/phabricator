<?php

final class PhabricatorLogoutController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    // See T13310. We allow access to the "Logout" controller even if you are
    // not logged in: otherwise, users who do not have access to any Spaces can
    // not log out.

    // When you try to access a controller which requires you be logged in,
    // and you do not have access to any Spaces, an access check fires first
    // and prevents access with a "No Access to Spaces" error. If this
    // controller requires users be logged in, users who are trying to log out
    // and also have no access to Spaces get the error instead of a logout
    // workflow and are trapped.

    // By permitting access to this controller even if you are not logged in,
    // we bypass the Spaces check and allow users who have no access to Spaces
    // to log out.

    // This incidentally allows users who are already logged out to access the
    // controller, but this is harmless: we just no-op these requests.

    return false;
  }

  public function shouldRequireEmailVerification() {
    // Allow unverified users to logout.
    return false;
  }

  public function shouldRequireEnabledUser() {
    // Allow disabled users to logout.
    return false;
  }

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if ($request->isFormPost()) {
      // Destroy the user's session in the database so logout works even if
      // their cookies have some issues. We'll detect cookie issues when they
      // try to login again and tell them to clear any junk.
      $phsid = $request->getCookie(PhabricatorCookies::COOKIE_SESSION);
      if (strlen($phsid)) {
        $session = id(new PhabricatorAuthSessionQuery())
          ->setViewer($viewer)
          ->withSessionKeys(array($phsid))
          ->executeOne();

        if ($session) {
          $engine = new PhabricatorAuthSessionEngine();
          $engine->logoutSession($viewer, $session);
        }
      }
      $request->clearCookie(PhabricatorCookies::COOKIE_SESSION);

      return id(new AphrontRedirectResponse())
        ->setURI('/auth/loggedout/');
    }


    if ($viewer->getPHID()) {
      $dialog = $this->newDialog()
        ->setTitle(pht('Log Out?'))
        ->appendParagraph(pht('Are you sure you want to log out?'))
        ->addCancelButton('/');

      $configs = id(new PhabricatorAuthProviderConfigQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->execute();
      if (!$configs) {
        $dialog
          ->appendRemarkup(
            pht(
              'WARNING: You have not configured any authentication providers '.
              'yet, so your account has no login credentials. If you log out '.
              'now, you will not be able to log back in normally.'))
          ->appendParagraph(
            pht(
              'To enable the login flow, follow setup guidance and configure '.
              'at least one authentication provider, then associate '.
              'credentials with your account. After completing these steps, '.
              'you will be able to log out and log back in normally.'))
          ->appendParagraph(
            pht(
              'If you log out now, you can still regain access to your '.
              'account later by using the account recovery workflow. The '.
              'login screen will prompt you with recovery instructions.'));

        $button = pht('Log Out Anyway');
      } else {
        $button = pht('Log Out');
      }

      $dialog->addSubmitButton($button);
      return $dialog;
    }

    return id(new AphrontRedirectResponse())->setURI('/');
  }

}
