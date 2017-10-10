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

    $request_method = idx($_SERVER, 'REQUEST_METHOD');
    if ($request_method === 'PUT') {
      // For PUT requests, do nothing: in particular, do NOT read input. This
      // allows us to stream input later and process very large PUT requests,
      // like those coming from Git LFS.
    } else {
      // For POST requests, we're going to read the raw input ourselves here
      // if we can. Among other things, this corrects variable names with
      // the "." character in them, which PHP normally converts into "_".

      // There are two major considerations here: whether the
      // `enable_post_data_reading` option is set, and whether the content
      // type is "multipart/form-data" or not.

      // If `enable_post_data_reading` is off, we're free to read the entire
      // raw request body and parse it -- and we must, because $_POST and
      // $_FILES are not built for us. If `enable_post_data_reading` is on,
      // which is the default, we may not be able to read the body (the
      // documentation says we can't, but empirically we can at least some
      // of the time).

      // If the content type is "multipart/form-data", we need to build both
      // $_POST and $_FILES, which is involved. The body itself is also more
      // difficult to parse than other requests.
      $raw_input = PhabricatorStartup::getRawInput();
      if (strlen($raw_input)) {
        $content_type = idx($_SERVER, 'CONTENT_TYPE');
        $is_multipart = preg_match('@^multipart/form-data@i', $content_type);
        if ($is_multipart && !ini_get('enable_post_data_reading')) {
          $multipart_parser = id(new AphrontMultipartParser())
            ->setContentType($content_type);

          $multipart_parser->beginParse();
          $multipart_parser->continueParse($raw_input);
          $parts = $multipart_parser->endParse();

          $query_string = array();
          foreach ($parts as $part) {
            if (!$part->isVariable()) {
              continue;
            }

            $name = $part->getName();
            $value = $part->getVariableValue();

            $query_string[] = urlencode($name).'='.urlencode($value);
          }
          $query_string = implode('&', $query_string);
          $post = $parser->parseQueryString($query_string);

          $files = array();
          foreach ($parts as $part) {
            if ($part->isVariable()) {
              continue;
            }

            $files[$part->getName()] = $part->getPHPFileDictionary();
          }
          $_FILES = $files;
        } else {
          $post = $parser->parseQueryString($raw_input);
        }

        $_POST = $post;
        PhabricatorStartup::rebuildRequest();

        $data += $post;
      } else if ($_POST) {
        $post = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        if (is_array($post)) {
          $_POST = $post;
          PhabricatorStartup::rebuildRequest();
        }
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
