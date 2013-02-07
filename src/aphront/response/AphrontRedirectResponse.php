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

      $link = phutil_tag(
        'a',
        array(
          'href' => $this->getURI(),
        ),
        'Continue to: '.$this->getURI());

      $error->appendChild(hsprintf(
        '<p>You were stopped here because <tt>debug.stop-on-redirect</tt> '.
        'is set in your configuration.</p>'.
        '<p>%s</p>',
        $link));

      $view->appendChild($error);

      return $view->render();
    }

    return '';
  }

}
