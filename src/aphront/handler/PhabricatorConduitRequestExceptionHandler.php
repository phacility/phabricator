<?php

final class PhabricatorConduitRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 100000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Responds to requests made by Conduit clients.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {
    return $request->isConduit();
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $response = id(new ConduitAPIResponse())
      ->setErrorCode(get_class($ex))
      ->setErrorInfo($ex->getMessage());

    return id(new AphrontJSONResponse())
      ->setAddJSONShield(false)
      ->setContent($response->toDictionary());
  }

}
