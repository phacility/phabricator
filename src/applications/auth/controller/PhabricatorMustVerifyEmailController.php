<?php

final class PhabricatorMustVerifyEmailController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    // NOTE: We don't technically need this since PhabricatorController forces
    // us here in either case, but it's more consistent with intent.
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $email = $user->loadPrimaryEmail();

    if ($email->getIsVerified()) {
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    $email_address = $email->getAddress();

    $sent = null;
    if ($request->isFormPost()) {
      $email->sendVerificationEmail($user);
      $sent = new AphrontErrorView();
      $sent->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $sent->setTitle(pht('Email Sent'));
      $sent->appendChild('<p>'.
        pht('Another verification email was sent to <strong>%s</strong>.',
        phutil_escape_html($email_address)).'</p>');
    }

    $error_view = new AphrontRequestFailureView();
    $error_view->setHeader(pht('Check Your Email'));
    $error_view->appendChild(
      '<p>'.
      pht('You must verify your email address to login. You should have a new '.
      'email message from Phabricator with verification instructions in your '.
      'inbox (<strong>%s</strong>).', phutil_escape_html($email_address)).
      '</p>');
    $error_view->appendChild(
      '<p>'.
      pht('If you did not receive an email, you can click the button below '.
      'to try sending another one.').
      '</p>');
    $error_view->appendChild(
      '<div class="aphront-failure-continue">'.
        phabricator_render_form(
          $user,
          array(
            'action' => '/login/mustverify/',
            'method' => 'POST',
          ),
          phutil_render_tag(
            'button',
            array(
            ),
            pht('Send Another Email'))).
      '</div>');


    return $this->buildApplicationPage(
      array(
        $sent,
        $error_view,
      ),
      array(
        'title' => pht('Must Verify Email'),
        'device' => true
      ));
  }

}
