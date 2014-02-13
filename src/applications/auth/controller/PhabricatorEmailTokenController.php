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

    if ($request->getUser()->isLoggedIn()) {
      return $this->renderError(
        pht('You are already logged in.'));
    }

    $token = $this->token;
    $email = $request->getStr('email');

    $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $email);

    $target_user = null;
    if ($target_email) {
      $target_user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $target_email->getUserPHID());
    }

    // NOTE: We need to bind verification to **addresses**, not **users**,
    // because we verify addresses when they're used to login this way, and if
    // we have a user-based verification you can:
    //
    //  - Add some address you do not own;
    //  - request a password reset;
    //  - change the URI in the email to the address you don't own;
    //  - login via the email link; and
    //  - get a "verified" address you don't control.

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
      $view->appendChild(phutil_tag_div(
        'aphront-failure-continue',
        phutil_tag(
          'a',
          array('class' => 'button', 'href' => '/login/email/'),
          pht('Send Another Email'))));

      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => pht('Login Failure'),
        ));
    }

    if ($request->isFormPost()) {
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

      PhabricatorCookies::setNextURICookie($request, $next, $force = true);

      return $this->loginUser($target_user);
    }

    // NOTE: We need to CSRF here so attackers can't generate an email link,
    // then log a user in to an account they control via sneaky invisible
    // form submissions.

    // TODO: Since users can arrive here either through password reset or
    // through welcome emails, it might be nice to include the workflow type
    // in the URI or query params so we can tailor the messaging. Right now,
    // it has to be generic enough to make sense in either workflow, which
    // leaves it feeling a little awkward.

    $dialog = id(new AphrontDialogView())
      ->setUser($request->getUser())
      ->setTitle(pht('Login to Phabricator'))
      ->addHiddenInput('email', $email)
      ->appendParagraph(
        pht(
          'Use the button below to log in as: %s',
          phutil_tag('strong', array(), $email)))
      ->appendParagraph(
        pht(
          'After logging in you should set a password for your account, or '.
          'link your account to an external account that you can use to '.
          'authenticate in the future.'))
      ->addSubmitButton(pht('Login (%s)', $email))
      ->addCancelButton('/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
