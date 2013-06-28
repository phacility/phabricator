<?php

final class PhabricatorEmailTokenController
  extends PhabricatorAuthController {

  private $token;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->token = $data['token'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $token = $this->token;
    $email = $request->getStr('email');

    // NOTE: We need to bind verification to **addresses**, not **users**,
    // because we verify addresses when they're used to login this way, and if
    // we have a user-based verification you can:
    //
    //  - Add some address you do not own;
    //  - request a password reset;
    //  - change the URI in the email to the address you don't own;
    //  - login via the email link; and
    //  - get a "verified" address you don't control.

    $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $email);

    $target_user = null;
    if ($target_email) {
      $target_user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $target_email->getUserPHID());
    }

    if (!$target_email ||
        !$target_user  ||
        !$target_user->validateEmailToken($target_email, $token)) {

      $view = new AphrontRequestFailureView();
      $view->setHeader(pht('Unable to Login'));
      $view->appendChild(phutil_tag('p', array(), pht(
        'The authentication information in the link you clicked is '.
        'invalid or out of date. Make sure you are copy-and-pasting the '.
        'entire link into your browser. You can try again, or request '.
        'a new email.')));
      $view->appendChild(hsprintf(
        '<div class="aphront-failure-continue">'.
          '<a class="button" href="/login/email/">%s</a>'.
        '</div>',
        pht('Send Another Email')));

      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => pht('Login Failure'),
        ));
    }

    // Verify email so that clicking the link in the "Welcome" email is good
    // enough, without requiring users to go through a second round of email
    // verification.

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $target_email->setIsVerified(1);
      $target_email->save();
    unset($unguarded);

    $next = '/';
    if (!PhabricatorAuthProviderPassword::getPasswordProvider()) {
      $next = '/settings/panel/external/';
    } else if (PhabricatorEnv::getEnvConfig('account.editable')) {
      $next = (string)id(new PhutilURI('/settings/panel/password/'))
        ->setQueryParams(
          array(
            'token' => $token,
            'email' => $email,
          ));
    }

    $request->setCookie('next_uri', $next);

    return $this->loginUser($target_user);
  }
}
