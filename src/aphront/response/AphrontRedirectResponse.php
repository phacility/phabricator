<?php

/**
 * TODO: Should be final but isn't because of AphrontReloadResponse.
 */
class AphrontRedirectResponse extends AphrontResponse {

  private $uri;
  private $stackWhenCreated;

  public function __construct() {
    if ($this->shouldStopForDebugging()) {
      // If we're going to stop, capture the stack so we can print it out.
      $this->stackWhenCreated = id(new Exception())->getTrace();
    }
  }

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
      $request = $this->getRequest();
      $viewer = $request->getUser();

      $view = new PhabricatorStandardPageView();
      $view->setRequest($this->getRequest());
      $view->setApplicationName(pht('Debug'));
      $view->setTitle(pht('Stopped on Redirect'));

      $dialog = new AphrontDialogView();
      $dialog->setUser($viewer);
      $dialog->setTitle(pht('Stopped on Redirect'));

      $dialog->appendParagraph(
        pht(
          'You were stopped here because %s is set in your configuration.',
          phutil_tag('tt', array(), 'debug.stop-on-redirect')));

      $dialog->appendParagraph(
        pht(
          'You are being redirected to: %s',
          phutil_tag('tt', array(), $this->getURI())));

      $dialog->addCancelButton($this->getURI(), pht('Continue'));

      $dialog->appendChild(phutil_tag('br'));

      $dialog->appendChild(
        id(new AphrontStackTraceView())
          ->setUser($viewer)
          ->setTrace($this->stackWhenCreated));

      $dialog->setIsStandalone(true);
      $dialog->setWidth(AphrontDialogView::WIDTH_FULL);

      $box = id(new PHUIBoxView())
        ->addMargin(PHUI::MARGIN_LARGE)
        ->appendChild($dialog);

      $view->appendChild($box);

      return $view->render();
    }

    return '';
  }

}
