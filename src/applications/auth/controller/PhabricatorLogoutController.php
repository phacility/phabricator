<?php

final class PhabricatorLogoutController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return true;
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
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {

      $log = PhabricatorUserLog::initializeNewLog(
        $user,
        $user->getPHID(),
        PhabricatorUserLog::ACTION_LOGOUT);
      $log->save();

      // Destroy the user's session in the database so logout works even if
      // their cookies have some issues. We'll detect cookie issues when they
      // try to login again and tell them to clear any junk.
      $phsid = $request->getCookie(PhabricatorCookies::COOKIE_SESSION);
      if (strlen($phsid)) {
        $session = id(new PhabricatorAuthSessionQuery())
          ->setViewer($user)
          ->withSessionKeys(array($phsid))
          ->executeOne();
        if ($session) {
          $session->delete();
        }
      }
      $request->clearCookie(PhabricatorCookies::COOKIE_SESSION);

      return id(new AphrontRedirectResponse())
        ->setURI('/auth/loggedout/');
    }

    if ($user->getPHID()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Log out of Phabricator?'))
        ->appendChild(pht('Are you sure you want to log out?'))
        ->addSubmitButton(pht('Logout'))
        ->addCancelButton('/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    return id(new AphrontRedirectResponse())->setURI('/');
  }

}
