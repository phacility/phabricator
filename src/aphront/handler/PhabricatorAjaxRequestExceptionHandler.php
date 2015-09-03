<?php

final class PhabricatorAjaxRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 110000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Responds to requests made by AJAX clients.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {
    // For non-workflow requests, return a Ajax response.
    return ($request->isAjax() && !$request->isWorkflow());
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    // Log these; they don't get shown on the client and can be difficult
    // to debug.
    phlog($ex);

    $response = new AphrontAjaxResponse();
    $response->setError(
      array(
        'code' => get_class($ex),
        'info' => $ex->getMessage(),
      ));
    return $response;
  }

}
