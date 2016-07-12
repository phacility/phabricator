<?php

/**
 * NOTE: Do not extend this!
 *
 * @concrete-extensible
 */
class AphrontDefaultApplicationConfiguration
  extends AphrontApplicationConfiguration {

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function buildRequest() {
    $parser = new PhutilQueryStringParser();
    $data   = array();

    // If the request has "multipart/form-data" content, we can't use
    // PhutilQueryStringParser to parse it, and the raw data supposedly is not
    // available anyway (according to the PHP documentation, "php://input" is
    // not available for "multipart/form-data" requests). However, it is
    // available at least some of the time (see T3673), so double check that
    // we aren't trying to parse data we won't be able to parse correctly by
    // examining the Content-Type header.
    $content_type = idx($_SERVER, 'CONTENT_TYPE');
    $is_form_data = preg_match('@^multipart/form-data@i', $content_type);

    $request_method = idx($_SERVER, 'REQUEST_METHOD');
    if ($request_method === 'PUT') {
      // For PUT requests, do nothing: in particular, do NOT read input. This
      // allows us to stream input later and process very large PUT requests,
      // like those coming from Git LFS.
    } else {
      $raw_input = PhabricatorStartup::getRawInput();
      if (strlen($raw_input) && !$is_form_data) {
        $data += $parser->parseQueryString($raw_input);
      } else if ($_POST) {
        $data += $_POST;
      }
    }

    $data += $parser->parseQueryString(idx($_SERVER, 'QUERY_STRING', ''));

    $cookie_prefix = PhabricatorEnv::getEnvConfig('phabricator.cookie-prefix');

    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($data);
    $request->setApplicationConfiguration($this);
    $request->setCookiePrefix($cookie_prefix);

    return $request;
  }

  public function build404Controller() {
    return array(new Phabricator404Controller(), array());
  }

  public function buildRedirectController($uri, $external) {
    return array(
      new PhabricatorRedirectController(),
      array(
        'uri' => $uri,
        'external' => $external,
      ),
    );
  }

}
