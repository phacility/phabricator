<?php

final class Aphront403Response extends AphrontHTMLResponse {

  private $forbiddenText;
  public function setForbiddenText($text) {
    $this->forbiddenText = $text;
    return $this;
  }
  private function getForbiddenText() {
    return $this->forbiddenText;
  }

  public function getHTTPResponseCode() {
    return 403;
  }

  public function buildResponseString() {
    $forbidden_text = $this->getForbiddenText();
    if (!$forbidden_text) {
      $forbidden_text =
        pht('You do not have privileges to access the requested page.');
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('403 Forbidden'))
      ->addCancelButton('/', pht('Peace Out'))
      ->appendParagraph($forbidden_text);

    $view = id(new PhabricatorStandardPageView())
      ->setTitle(pht('403 Forbidden'))
      ->setRequest($request)
      ->setDeviceReady(true)
      ->appendChild($dialog);

    return $view->render();
  }

}
