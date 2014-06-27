<?php

final class PhabricatorAuthNeedsApprovalController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $wait_for_approval = pht(
      "Your account has been created, but needs to be approved by an ".
      "administrator. You'll receive an email once your account is approved.");

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Wait for Approval'))
      ->appendChild($wait_for_approval)
      ->addCancelButton('/', pht('Wait Patiently'));

    return $this->buildApplicationPage(
      $dialog,
      array(
        'title' => pht('Wait For Approval'),
      ));
  }

}
