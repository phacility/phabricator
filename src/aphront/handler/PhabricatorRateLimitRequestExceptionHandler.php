<?php

final class PhabricatorRateLimitRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 300000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht(
      'Handles action rate limiting exceptions which occur when a user '.
      'does something too frequently.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return ($ex instanceof PhabricatorSystemActionRateLimitException);
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $viewer = $this->getViewer($request);

    return id(new AphrontDialogView())
      ->setTitle(pht('Slow Down!'))
      ->setUser($viewer)
      ->setErrors(array(pht('You are being rate limited.')))
      ->appendParagraph($ex->getMessage())
      ->appendParagraph($ex->getRateExplanation())
      ->addCancelButton('/', pht('Okaaaaaaaaaaaaaay...'));
  }

}
