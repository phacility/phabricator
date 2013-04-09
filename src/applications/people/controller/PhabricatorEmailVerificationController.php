<?php

final class PhabricatorEmailVerificationController
  extends PhabricatorPeopleController {

  private $code;

  public function willProcessRequest(array $data) {
    $this->code = $data['code'];
  }

  public function shouldRequireAdmin() {
    return false;
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

    $home_link = phutil_tag(
      'a',
      array(
        'href' => '/',
      ),
      pht('Continue to Phabricator'));
    $home_link = hsprintf(
      '<br /><p><strong>%s</strong></p>',
      $home_link);

    $settings_link = phutil_tag(
      'a',
      array(
        'href' => '/settings/panel/email/',
      ),
      pht('Return to Email Settings'));
    $settings_link = hsprintf(
      '<br /><p><strong>%s</strong></p>',
      $settings_link);

    if (!$email) {
      $content = id(new AphrontErrorView())
        ->setTitle(pht('Unable To Verify'))
        ->appendChild(phutil_tag(
          'p',
          array(),
          pht('The verification code is incorrect, the email address has been '.
            'removed, or the email address is owned by another user. Make '.
            'sure you followed the link in the email correctly.')));
    } else if ($email->getIsVerified()) {
      $inst = pht('This email address has already been verified.');
      $content = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Address Already Verified'))
        ->appendChild(hsprintf(
          '<p>%s</p>%s',
          $inst,
          $settings_link));
    } else {

      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
        $email->setIsVerified(1);
        $email->save();
      unset($guard);

      $inst = pht('This email address has now been verified. Thanks!');
      $content = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Address Verified'))
        ->appendChild(hsprintf(
          '<p>%s</p>%s%s',
          $inst,
          $home_link,
          $settings_link));
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => pht('Verify Email'),
        'device' => true,
      ));
  }

}
