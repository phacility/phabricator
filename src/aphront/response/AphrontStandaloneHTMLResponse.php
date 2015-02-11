<?php

abstract class AphrontStandaloneHTMLResponse
  extends AphrontHTMLResponse {

  abstract protected function getResources();
  abstract protected function getResponseTitle();
  abstract protected function getResponseBodyClass();
  abstract protected function getResponseBody();
  abstract protected function buildPlainTextResponseString();

  final public function buildResponseString() {
    // Check to make sure we aren't requesting this via Ajax or Conduit.
    if (isset($_REQUEST['__ajax__']) || isset($_REQUEST['__conduit__'])) {
      return (string)hsprintf('%s', $this->buildPlainTextResponseString());
    }

    $title = $this->getResponseTitle();
    $resources = $this->buildResources();
    $body_class = $this->getResponseBodyClass();
    $body = $this->getResponseBody();

    return (string)hsprintf(
<<<EOTEMPLATE
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>%s</title>
    %s
  </head>
  %s
</html>
EOTEMPLATE
      ,
      $title,
      $resources,
      phutil_tag(
        'body',
        array(
          'class' => $body_class,
        ),
        $body));
  }

  private function buildResources() {
    $paths = $this->getResources();

    $webroot = dirname(phutil_get_library_root('phabricator')).'/webroot/';

    $resources = array();
    foreach ($paths as $path) {
      $resources[] = phutil_tag(
        'style',
        array('type' => 'text/css'),
        phutil_safe_html(Filesystem::readFile($webroot.'/rsrc/'.$path)));
    }

    return phutil_implode_html("\n", $resources);
  }


}
