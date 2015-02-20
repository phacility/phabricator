<?php

final class PhabricatorApplicationTransactionValidationResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $exception;
  private $cancelURI;

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function setException(
    PhabricatorApplicationTransactionValidationException $exception) {
    $this->exception = $exception;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontDialogResponse();
  }

  public function reduceProxyResponse() {
    $request = $this->getRequest();

    $ex = $this->exception;
    $title = pht('Validation Errors');

    $dialog = id(new AphrontDialogView())
      ->setUser($request->getUser())
      ->setTitle($title);

    $list = array();
    foreach ($ex->getErrors() as $error) {
      $list[] = phutil_tag(
        'li',
        array(),
        $error->getMessage());
    }

    $dialog->appendChild(phutil_tag('ul', array(), $list));
    $dialog->addCancelButton($this->cancelURI);

    return $this->getProxy()->setDialog($dialog);
  }

}
