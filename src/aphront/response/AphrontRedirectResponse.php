<?php

/**
 * TODO: Should be final but isn't because of AphrontReloadResponse.
 */
class AphrontRedirectResponse extends AphrontResponse {

  private $uri;
  private $stackWhenCreated;
  private $isExternal;
  private $closeDialogBeforeRedirect;

  public function setIsExternal($external) {
    $this->isExternal = $external;
    return $this;
  }

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
    // NOTE: When we convert a RedirectResponse into an AjaxResponse, we pull
    // the URI through this method. Make sure it passes checks before we
    // hand it over to callers.
    return self::getURIForRedirect($this->uri, $this->isExternal);
  }

  public function shouldStopForDebugging() {
    return PhabricatorEnv::getEnvConfig('debug.stop-on-redirect');
  }

  public function setCloseDialogBeforeRedirect($close) {
    $this->closeDialogBeforeRedirect = $close;
    return $this;
  }

  public function getCloseDialogBeforeRedirect() {
    return $this->closeDialogBeforeRedirect;
  }

  public function getHeaders() {
    $headers = array();
    if (!$this->shouldStopForDebugging()) {
      $uri = self::getURIForRedirect($this->uri, $this->isExternal);
      $headers[] = array('Location', $uri);
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


  /**
   * Format a URI for use in a "Location:" header.
   *
   * Verifies that a URI redirects to the expected type of resource (local or
   * remote) and formats it for use in a "Location:" header.
   *
   * The HTTP spec says "Location:" headers must use absolute URIs. Although
   * browsers work with relative URIs, we return absolute URIs to avoid
   * ambiguity. For example, Chrome interprets "Location: /\evil.com" to mean
   * "perform a protocol-relative redirect to evil.com".
   *
   * @param   string  URI to redirect to.
   * @param   bool    True if this URI identifies a remote resource.
   * @return  string  URI for use in a "Location:" header.
   */
  public static function getURIForRedirect($uri, $is_external) {
    $uri_object = new PhutilURI($uri);
    if ($is_external) {
      // If this is a remote resource it must have a domain set. This
      // would also be caught below, but testing for it explicitly first allows
      // us to raise a better error message.
      if (!strlen($uri_object->getDomain())) {
        throw new Exception(
          pht(
            'Refusing to redirect to external URI "%s". This URI '.
            'is not fully qualified, and is missing a domain name. To '.
            'redirect to a local resource, remove the external flag.',
            (string)$uri));
      }

      // Check that it's a valid remote resource.
      if (!PhabricatorEnv::isValidURIForLink($uri)) {
        throw new Exception(
          pht(
            'Refusing to redirect to external URI "%s". This URI '.
            'is not a valid remote web resource.',
            (string)$uri));
      }
    } else {
      // If this is a local resource, it must not have a domain set. This allows
      // us to raise a better error message than the check below can.
      if (strlen($uri_object->getDomain())) {
        throw new Exception(
          pht(
            'Refusing to redirect to local resource "%s". The URI has a '.
            'domain, but the redirect is not marked external. Mark '.
            'redirects as external to allow redirection off the local '.
            'domain.',
            (string)$uri));
      }

      // If this is a local resource, it must be a valid local resource.
      if (!PhabricatorEnv::isValidLocalURIForLink($uri)) {
        throw new Exception(
          pht(
            'Refusing to redirect to local resource "%s". This URI is not '.
            'formatted in a recognizable way.',
            (string)$uri));
      }

      // Fully qualify the result URI.
      $uri = PhabricatorEnv::getURI((string)$uri);
    }

    return (string)$uri;
  }

}
