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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {

      $log = PhabricatorUserLog::newLog(
        $user,
        $user,
        PhabricatorUserLog::ACTION_LOGOUT);
      $log->save();

      // Destroy the user's session in the database so logout works even if
      // their cookies have some issues. We'll detect cookie issues when they
      // try to login again and tell them to clear any junk.
      $phsid = $request->getCookie('phsid');
      if ($phsid) {
        $user->destroySession($phsid);
      }
      $request->clearCookie('phsid');

      return id(new AphrontRedirectResponse())
        ->setURI('/login/');
    }

    if ($user->getPHID()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle('Log out of Phabricator?')
        ->appendChild('<p>Are you sure you want to log out?</p>')
        ->addSubmitButton('Log Out')
        ->addCancelButton('/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    return id(new AphrontRedirectResponse())->setURI('/');
  }

}
