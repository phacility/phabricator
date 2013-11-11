<?php

/**
 * TODO: Should be final but isn't because of AphrontReloadResponse.
 *
 * @group aphront
 */
class AphrontRedirectResponse extends AphrontResponse {

  private $uri;

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return (string)$this->uri;
  }

  public function shouldStopForDebugging() {
    return PhabricatorEnv::getEnvConfig('debug.stop-on-redirect');
  }

  public function getHeaders() {
    $headers = array();
    if (!$this->shouldStopForDebugging()) {
      $headers[] = array('Location', $this->uri);
    }
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

  public function buildResponseString() {
    if ($this->shouldStopForDebugging()) {
      $view = new PhabricatorStandardPageView();
      $view->setRequest($this->getRequest());
      $view->setApplicationName('Debug');
      $view->setTitle('Stopped on Redirect');

      $error = new AphrontErrorView();
      $error->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $error->setTitle('Stopped on Redirect');

      $error->appendChild(phutil_tag('p', array(), pht(
        'You were stopped here because %s is set in your configuration.',
        phutil_tag('tt', array(), 'debug.stop-on-redirect'))));

      $link = phutil_tag(
        'a',
        array(
          'href' => $this->getURI(),
        ),
        $this->getURI());

      $error->appendChild(phutil_tag('p', array(), pht(
        'Continue to: %s',
        $link)));

      $view->appendChild($error);

      return $view->render();
    }

    return '';
  }

}
