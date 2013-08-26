<?php

final class PhabricatorEmailVerificationController
  extends PhabricatorAuthController {

  private $code;

  public function willProcessRequest(array $data) {
    $this->code = $data['code'];
  }

  public function shouldRequireEmailVerification() {
    // Since users need to be able to hit this endpoint in order to verify
    // email, we can't ever require email verification here.
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'userPHID = %s AND verificationCode = %s',
      $user->getPHID(),
      $this->code);

    if (!$email) {
      $title = pht('Unable to Verify Email');
      $content = pht(
        'The verification code you provided is incorrect, or the email '.
        'address has been removed, or the email address is owned by another '.
        'user. Make sure you followed the link in the email correctly and are '.
        'logged in with the user account associated with the email address.');
      $continue = pht('Rats!');
    } else if ($email->getIsVerified()) {
      $title = pht('Address Already Verified');
      $content = pht(
        'This email address has already been verified.');
      $continue = pht('Continue to Phabricator');
    } else {
      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
        $email->setIsVerified(1);
        $email->save();
      unset($guard);

      $title = pht('Address Verified');
      $content = pht(
        'The email address %s is now verified.',
        phutil_tag('strong', array(), $email->getAddress()));
      $continue = pht('Continue to Phabricator');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle($title)
      ->setMethod('GET')
      ->addCancelButton('/', $continue)
      ->appendChild($content);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Verify Email')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $dialog,
      ),
      array(
        'title' => pht('Verify Email'),
        'device' => true,
      ));
  }

}
