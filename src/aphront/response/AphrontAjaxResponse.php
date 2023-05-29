<?php

final class AphrontAjaxResponse extends AphrontResponse {

  private $content;
  private $error;
  private $disableConsole;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setError($error) {
    $this->error = $error;
    return $this;
  }

  public function setDisableConsole($disable) {
    $this->disableConsole = $disable;
    return $this;
  }

  private function getConsole() {
    if ($this->disableConsole) {
      $console = null;
    } else {
      $request = $this->getRequest();
      $console = $request->getApplicationConfiguration()->getConsole();
    }
    return $console;
  }

  public function buildResponseString() {
    $request = $this->getRequest();
    $console = $this->getConsole();
    if ($console) {
      // NOTE: We're stripping query parameters here both for readability and
      // to mitigate BREACH and similar attacks. The parameters are available
      // in the "Request" tab, so this should not impact usability. See T3684.
      $path = $request->getPath();

      Javelin::initBehavior(
        'dark-console',
        array(
          'uri'       => $path,
          'key'       => $console->getKey($request),
          'color'     => $console->getColor(),
          'quicksand' => $request->isQuicksand(),
        ));
    }

    // Flatten the response first, so we initialize any behaviors and metadata
    // we need to.
    $content = array(
      'payload' => $this->content,
    );
    $this->encodeJSONForHTTPResponse($content);

    $response = CelerityAPI::getStaticResourceResponse();

    if ($request) {
      $viewer = $request->getViewer();
      if ($viewer) {
        $postprocessor_key = $viewer->getUserSetting(
          PhabricatorAccessibilitySetting::SETTINGKEY);
        if ($postprocessor_key !== null && strlen($postprocessor_key)) {
          $response->setPostprocessorKey($postprocessor_key);
        }
      }
    }

    $object = $response->buildAjaxResponse(
      $content['payload'],
      $this->error);

    $response_json = $this->encodeJSONForHTTPResponse($object);
    return $this->addJSONShield($response_json);
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'text/plain; charset=UTF-8'),
    );
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
