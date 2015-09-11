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

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return ($ex instanceof PhabricatorAuthHighSecurityRequiredException);
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $viewer = $this->getViewer($request);

    $form = id(new PhabricatorAuthSessionEngine())->renderHighSecurityForm(
      $ex->getFactors(),
      $ex->getFactorValidationResults(),
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
      ->addCancelButton($ex->getCancelURI())
      ->addSubmitButton(pht('Enter High Security'));

    $request_parameters = $request->getPassthroughRequestParameters(
      $respect_quicksand = true);
    foreach ($request_parameters as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }

}
