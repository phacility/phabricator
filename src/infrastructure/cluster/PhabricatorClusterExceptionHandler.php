<?php

final class PhabricatorClusterExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 300000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Handles runtime problems with cluster configuration.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {
    return ($ex instanceof PhabricatorClusterException);
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $viewer = $this->getViewer($request);

    $title = $ex->getExceptionTitle();

    $dialog =  id(new AphrontDialogView())
      ->setTitle($title)
      ->setUser($viewer)
      ->appendParagraph($ex->getMessage())
      ->addCancelButton('/', pht('Proceed With Caution'));

    return id(new AphrontDialogResponse())
      ->setDialog($dialog)
      ->setHTTPResponseCode(500);
  }

}
