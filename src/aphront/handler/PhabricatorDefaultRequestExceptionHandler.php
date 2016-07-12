<?php

final class PhabricatorDefaultRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 900000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht('Handles all other exceptions.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return true;
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $viewer = $this->getViewer($request);

    // Always log the unhandled exception.
    phlog($ex);

    $class = get_class($ex);
    $message = $ex->getMessage();

    if ($ex instanceof AphrontSchemaQueryException) {
      $message .= "\n\n".pht(
        "NOTE: This usually indicates that the MySQL schema has not been ".
        "properly upgraded. Run '%s' to ensure your schema is up to date.",
        'bin/storage upgrade');
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $trace = id(new AphrontStackTraceView())
        ->setUser($viewer)
        ->setTrace($ex->getTrace());
    } else {
      $trace = null;
    }

    $content = phutil_tag(
      'div',
      array('class' => 'aphront-unhandled-exception'),
      array(
        phutil_tag('div', array('class' => 'exception-message'), $message),
        $trace,
      ));

    $dialog = new AphrontDialogView();
    $dialog
      ->setTitle(pht('Unhandled Exception ("%s")', $class))
      ->setClass('aphront-exception-dialog')
      ->setUser($viewer)
      ->appendChild($content);

    if ($request->isAjax()) {
      $dialog->addCancelButton('/', pht('Close'));
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialog)
      ->setHTTPResponseCode(500);
  }

}
