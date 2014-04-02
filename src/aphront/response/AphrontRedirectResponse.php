<?php

/**
 * TODO: Should be final but isn't because of AphrontReloadResponse.
 *
 * @group aphront
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
      if ($request) {
        $user = $request->getUser();
      }
      if (!isset($user)) {
        $user = new PhabricatorUser();
        // This fake user needs to be able to generate a CSRF token.
        $session_key = Filesystem::readRandomCharacters(40);
        $user->attachAlternateCSRFString(PhabricatorHash::digest($session_key));
      }

      $view = new PhabricatorStandardPageView();
      $view->setRequest($request);
      $view->setApplicationName('Debug');
      $view->setTitle('Stopped on Redirect');

      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Stopped on Redirect');

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
          ->setUser($user)
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
