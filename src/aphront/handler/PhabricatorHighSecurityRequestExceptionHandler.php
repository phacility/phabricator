<?php

final class PhabricatorHighSecurityRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 310000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht(
      'Handles high security exceptions which occur when a user needs '.
      'to present MFA credentials to take an action.');
  }

  public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return ($throwable instanceof PhabricatorAuthHighSecurityRequiredException);
  }

  public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    $viewer = $this->getViewer($request);
    $results = $throwable->getFactorValidationResults();

    $form = id(new PhabricatorAuthSessionEngine())->renderHighSecurityForm(
      $throwable->getFactors(),
      $results,
      $viewer,
      $request);

    $is_wait = false;
    $is_continue = false;
    foreach ($results as $result) {
      if ($result->getIsWait()) {
        $is_wait = true;
      }

      if ($result->getIsContinue()) {
        $is_continue = true;
      }
    }

    $is_upgrade = $throwable->getIsSessionUpgrade();

    if ($is_upgrade) {
      $title = pht('Enter High Security');
    } else {
      $title = pht('Provide MFA Credentials');
    }

    if ($is_wait) {
      $submit = pht('Wait Patiently');
    } else if ($is_upgrade && !$is_continue) {
      $submit = pht('Enter High Security');
    } else {
      $submit = pht('Continue');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->setShortTitle(pht('Security Checkpoint'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addHiddenInput(AphrontRequest::TYPE_HISEC, true)
      ->setSubmitURI($request->getPath())
      ->addCancelButton($throwable->getCancelURI())
      ->addSubmitButton($submit);

    $form_layout = $form->buildLayoutView();

    if ($is_upgrade) {
      $message = pht(
        'You are taking an action which requires you to enter '.
        'high security.');

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_MFA)
        ->setErrors(array($message));

      $dialog
        ->appendChild($info_view)
        ->appendParagraph(
          pht(
            'To enter high security mode, confirm your credentials:'))
        ->appendChild($form_layout)
        ->appendParagraph(
          pht(
            'Your account will remain in high security mode for a short '.
            'period of time. When you are finished taking sensitive '.
            'actions, you should leave high security.'));
    } else {
      $message = pht(
        'You are taking an action which requires you to provide '.
        'multi-factor credentials.');

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_MFA)
        ->setErrors(array($message));

      $dialog
        ->appendChild($info_view)
        ->setErrors(
          array(
          ))
        ->appendChild($form_layout);
    }

    $request_parameters = $request->getPassthroughRequestParameters(
      $respect_quicksand = true);
    foreach ($request_parameters as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    // See T13289. If the user hit a "some transactions have no effect" dialog
    // and elected to continue, we want to pass that flag through the MFA
    // dialog even though it is not normally a passthrough request parameter.
    if ($request->isContinueRequest()) {
      $dialog->addHiddenInput(AphrontRequest::TYPE_CONTINUE, 1);
    }

    return $dialog;
  }

}
