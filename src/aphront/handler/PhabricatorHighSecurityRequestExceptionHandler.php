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

    $form = id(new PhabricatorAuthSessionEngine())->renderHighSecurityForm(
      $throwable->getFactors(),
      $throwable->getFactorValidationResults(),
      $viewer,
      $request);

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Entering High Security'))
      ->setShortTitle(pht('Security Checkpoint'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addHiddenInput(AphrontRequest::TYPE_HISEC, true)
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
      ->appendChild($form->buildLayoutView())
      ->appendParagraph(
        pht(
          'Your account will remain in high security mode for a short '.
          'period of time. When you are finished taking sensitive '.
          'actions, you should leave high security.'))
      ->setSubmitURI($request->getPath())
      ->addCancelButton($throwable->getCancelURI())
      ->addSubmitButton(pht('Enter High Security'));

    $request_parameters = $request->getPassthroughRequestParameters(
      $respect_quicksand = true);
    foreach ($request_parameters as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }

}
