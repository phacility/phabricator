<?php

/**
 * @group aphront
 */
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
        'You do not have privileges to access the requested page.';
    }
    $failure = new AphrontRequestFailureView();
    $failure->setHeader('403 Forbidden');
    $failure->appendChild(phutil_tag('p', array(), $forbidden_text));

    $view = new PhabricatorStandardPageView();
    $view->setTitle('403 Forbidden');
    $view->setRequest($this->getRequest());
    $view->appendChild($failure);

    return $view->render();
  }

}
