<?php

final class PhabricatorConfigResponse extends AphrontStandaloneHTMLResponse {

  private $view;

  public function setView(PhabricatorSetupIssueView $view) {
    $this->view = $view;
    return $this;
  }

  public function getHTTPResponseCode() {
    return 500;
  }

  protected function getResources() {
    return array(
      'css/application/config/config-template.css',
      'css/application/config/setup-issue.css',
    );
  }

  protected function getResponseTitle() {
    return pht('Phabricator Setup Error');
  }

  protected function getResponseBodyClass() {
    return 'setup-fatal';
  }

  protected function getResponseBody() {
    return $this->view->render();
  }

  protected function buildPlainTextResponseString() {
    return pht(
      'This install has a fatal setup error, access the web interface '.
      'to view details and resolve it.');
  }

}
