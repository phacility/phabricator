<?php

final class Aphront404Response extends AphrontHTMLResponse {

  public function getHTTPResponseCode() {
    return 404;
  }

  public function buildResponseString() {
    $failure = id(new AphrontRequestFailureView())
      ->setHeader(pht('404 Not Found'))
      ->appendChild(phutil_tag('p', array(), pht(
      'The page you requested was not found.')));

    $view = id(new PhabricatorStandardPageView())
      ->setTitle('404 Not Found')
      ->setRequest($this->getRequest())
      ->setDeviceReady(true)
      ->appendChild($failure);

    return $view->render();
  }

}
