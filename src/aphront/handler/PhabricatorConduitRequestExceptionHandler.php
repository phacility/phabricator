<?php

final class PhabricatorConduitRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 100000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Responds to requests made by Conduit clients.');
  }

  public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable) {
    return $request->isConduit();
  }

  public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    $response = id(new ConduitAPIResponse())
      ->setErrorCode(get_class($throwable))
      ->setErrorInfo($throwable->getMessage());

    return id(new AphrontJSONResponse())
      ->setAddJSONShield(false)
      ->setContent($response->toDictionary());
  }

}
