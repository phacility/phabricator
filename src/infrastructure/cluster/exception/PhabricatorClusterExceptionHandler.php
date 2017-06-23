<?php

final class PhabricatorClusterExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 300000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Handles runtime problems with cluster configuration.');
  }

  public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable) {
    return ($throwable instanceof PhabricatorClusterException);
  }

  public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable) {

    $viewer = $this->getViewer($request);

    $title = $throwable->getExceptionTitle();

    $dialog =  id(new AphrontDialogView())
      ->setTitle($title)
      ->setUser($viewer)
      ->appendParagraph($throwable->getMessage())
      ->addCancelButton('/', pht('Proceed With Caution'));

    return id(new AphrontDialogResponse())
      ->setDialog($dialog)
      ->setHTTPResponseCode(500);
  }

}
