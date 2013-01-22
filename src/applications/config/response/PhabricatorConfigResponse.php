<?php

final class PhabricatorConfigResponse extends AphrontHTMLResponse {

  private $view;

  public function setView(PhabricatorSetupIssueView $view) {
    $this->view = $view;
    return $this;
  }

  public function buildResponseString() {
    $resources = $this->buildResources();

    $view = $this->view->render();

    $template = <<<EOTEMPLATE
<!doctype html>
<html>
  <head>
    <title>Phabricator Setup</title>
    {$resources}
  </head>
  <body class="setup-fatal">
    {$view}
  </body>
</html>
EOTEMPLATE;

    return $template;
  }

  private function buildResources() {
    $css = array(
      'application/config/config-template.css',
      'application/config/setup-issue.css',
    );

    $webroot = dirname(phutil_get_library_root('phabricator')).'/webroot/';

    $resources = array();
    foreach ($css as $path) {
      $resources[] = '<style type="text/css">';
      $resources[] = Filesystem::readFile($webroot.'/rsrc/css/'.$path);
      $resources[] = '</style>';
    }
    return implode("\n", $resources);
  }


}
