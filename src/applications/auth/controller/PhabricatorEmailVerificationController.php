<?php

final class PhabricatorEmailVerificationController
  extends PhabricatorAuthController {

  public function shouldRequireEmailVerification() {
    // Since users need to be able to hit this endpoint in order to verify
    // email, we can't ever require email verification here.
    return false;
  }

  public function shouldRequireEnabledUser() {
    // Unapproved users are allowed to verify their email addresses. We'll kick
    // disabled users out later.
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $code = $request->getURIData('code');

    if ($viewer->getIsDisabled()) {
      // We allowed unapproved and disabled users to hit this controller, but
      // want to kick out disabled users now.
      return new Aphront400Response();
    }

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'userPHID = %s AND verificationCode = %s',
      $viewer->getPHID(),
      $code);

    $submit = null;

    if (!$email) {
      $title = pht('Unable to Verify Email');
      $content = pht(
        'The verification code you provided is incorrect, or the email '.
        'address has been removed, or the email address is owned by another '.
        'user. Make sure you followed the link in the email correctly and are '.
        'logged in with the user account associated with the email address.');
      $continue = pht('Rats!');
    } else if ($email->getIsVerified() && $viewer->getIsEmailVerified()) {
      $title = pht('Address Already Verified');
      $content = pht(
        'This email address has already been verified.');
      $continue = pht('Continue to Phabricator');
    } else if ($request->isFormPost()) {

      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->verifyEmail($viewer, $email);

      $title = pht('Address Verified');
      $content = pht(
        'The email address %s is now verified.',
        phutil_tag('strong', array(), $email->getAddress()));
      $continue = pht('Continue to Phabricator');
    } else {
      $title = pht('Verify Email Address');
      $content = pht(
        'Verify this email address (%s) and attach it to your account?',
        phutil_tag('strong', array(), $email->getAddress()));
      $continue = pht('Cancel');
      $submit = pht('Verify %s', $email->getAddress());
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->addCancelButton('/', $continue)
      ->appendChild($content);

    if ($submit) {
      $dialog->addSubmitButton($submit);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Verify Email'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $dialog,
      ),
      array(
        'title' => pht('Verify Email'),
      ));
  }

}
