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

    $instructions = $this->newCustomWaitForApprovalInstructions();

    $wait_for_approval = pht(
      "Your account has been created, but needs to be approved by an ".
      "administrator. You'll receive an email once your account is approved.");

    $dialog = $this->newDialog()
      ->setTitle(pht('Wait for Approval'))
      ->appendChild($wait_for_approval)
      ->addCancelButton('/', pht('Wait Patiently'));

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Wait For Approval'))
      ->setBorder(true);

    return $this->newPage()
      ->setTitle(pht('Wait For Approval'))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $instructions,
          $dialog,
        ));

  }

  private function newCustomWaitForApprovalInstructions() {
    $viewer = $this->getViewer();

    $text = PhabricatorAuthMessage::loadMessageText(
      $viewer,
      PhabricatorAuthWaitForApprovalMessageType::MESSAGEKEY);

    if (!strlen($text)) {
      return null;
    }

    $remarkup_view = new PHUIRemarkupView($viewer, $text);

    return phutil_tag(
      'div',
      array(
        'class' => 'auth-custom-message',
      ),
      $remarkup_view);
  }

}
