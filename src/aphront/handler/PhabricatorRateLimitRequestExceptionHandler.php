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

  public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return ($throwable instanceof PhabricatorSystemActionRateLimitException);
  }

  public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    $viewer = $this->getViewer($request);

    return id(new AphrontDialogView())
      ->setTitle(pht('Slow Down!'))
      ->setUser($viewer)
      ->setErrors(array(pht('You are being rate limited.')))
      ->appendParagraph($throwable->getMessage())
      ->appendParagraph($throwable->getRateExplanation())
      ->addCancelButton('/', pht('Okaaaaaaaaaaaaaay...'));
  }

}
