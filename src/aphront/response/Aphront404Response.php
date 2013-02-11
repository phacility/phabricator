<?php

/**
 * @group aphront
 */
final class Aphront404Response extends AphrontHTMLResponse {

  public function getHTTPResponseCode() {
    return 404;
  }

  public function buildResponseString() {
    $failure = new AphrontRequestFailureView();
    $failure->setHeader('404 Not Found');
    $failure->appendChild(phutil_tag('p', array(), pht(
      'The page you requested was not found.')));

    $view = new PhabricatorStandardPageView();
    $view->setTitle('404 Not Found');
    $view->setRequest($this->getRequest());
    $view->appendChild($failure);

    return $view->render();
  }

}
