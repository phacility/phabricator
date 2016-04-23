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

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $wait_for_approval = pht(
      "Your account has been created, but needs to be approved by an ".
      "administrator. You'll receive an email once your account is approved.");

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Wait for Approval'))
      ->appendChild($wait_for_approval)
      ->addCancelButton('/', pht('Wait Patiently'));

    return $this->newPage()
      ->setTitle(pht('Wait For Approval'))
      ->appendChild($dialog);

  }

}
