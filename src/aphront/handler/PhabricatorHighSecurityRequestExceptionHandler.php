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
    foreach ($results as $result) {
      if ($result->getIsWait()) {
        $is_wait = true;
        break;
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
    } else if ($is_upgrade) {
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
      $dialog
        ->setErrors(
          array(
            pht(
              'You are taking an action which requires you to enter '.
              'high security.'),
          ))
        ->appendParagraph(
          pht(
            'High security mode helps protect your account from security '.
            'threats, like session theft or someone messing with your stuff '.
            'while you\'re grabbing a coffee. To enter high security mode, '.
            'confirm your credentials.'))
        ->appendChild($form_layout)
        ->appendParagraph(
          pht(
            'Your account will remain in high security mode for a short '.
            'period of time. When you are finished taking sensitive '.
            'actions, you should leave high security.'));
    } else {
      $dialog
        ->setErrors(
          array(
            pht(
              'You are taking an action which requires you to provide '.
              'multi-factor credentials.'),
          ))
        ->appendChild($form_layout);
    }

    $request_parameters = $request->getPassthroughRequestParameters(
      $respect_quicksand = true);
    foreach ($request_parameters as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }

}
