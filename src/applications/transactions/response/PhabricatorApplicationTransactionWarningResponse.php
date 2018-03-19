<?php

final class PhabricatorApplicationTransactionWarningResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $exception;
  private $cancelURI;

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function setException(
    PhabricatorApplicationTransactionWarningException $exception) {
    $this->exception = $exception;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontDialogResponse();
  }

  public function reduceProxyResponse() {
    $request = $this->getRequest();

    $title = pht('Warning: Unexpected Effects');

    $head = pht(
      'This is a draft revision that will not publish any notifications '.
      'until the author requests review.');
    $tail = pht(
      'Mentioned or subscribed users will not be notified.');

    $continue = pht('Tell No One');

    $dialog = id(new AphrontDialogView())
      ->setViewer($request->getViewer())
      ->setTitle($title);

    $dialog->appendParagraph($head);
    $dialog->appendParagraph($tail);

    $passthrough = $request->getPassthroughRequestParameters();
    foreach ($passthrough as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    $dialog
      ->addHiddenInput('editEngine.warnings', 1)
      ->addSubmitButton($continue)
      ->addCancelButton($this->cancelURI);

    return $this->getProxy()->setDialog($dialog);
  }

}
