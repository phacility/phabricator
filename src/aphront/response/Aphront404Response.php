<?php

/**
 * @group aphront
 */
final class Aphront404Response extends AphrontWebpageResponse {

  public function getHTTPResponseCode() {
    return 404;
  }

  public function buildResponseString() {
    $failure = new AphrontRequestFailureView();
    $failure->setHeader('404 Not Found');
    $failure->appendChild('<p>The page you requested was not found.</p>');

    $view = new PhabricatorStandardPageView();
    $view->setTitle('404 Not Found');
    $view->setRequest($this->getRequest());
    $view->appendChild($failure);

    return $view->render();
  }

}
