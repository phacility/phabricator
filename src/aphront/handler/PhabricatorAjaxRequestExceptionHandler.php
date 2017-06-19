<?php

final class PhabricatorAjaxRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 110000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Responds to requests made by AJAX clients.');
  }

  public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable) {
    // For non-workflow requests, return a Ajax response.
    return ($request->isAjax() && !$request->isWorkflow());
  }

  public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    // Log these; they don't get shown on the client and can be difficult
    // to debug.
    phlog($throwable);

    $response = new AphrontAjaxResponse();
    $response->setError(
      array(
        'code' => get_class($throwable),
        'info' => $throwable->getMessage(),
      ));

    return $response;
  }

}
