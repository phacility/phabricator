<?php

/**
 * @group aphront
 */
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
    $console = $this->getConsole();
    if ($console) {
      Javelin::initBehavior(
        'dark-console',
        array(
          'uri'     => (string)$this->getRequest()->getRequestURI(),
          'key'     => $console->getKey($this->getRequest()),
          'color'   => $console->getColor(),
        ));
    }

    // Flatten the response first, so we initialize any behaviors and metadata
    // we need to.
    $content = array(
      'payload' => $this->content,
    );
    $this->encodeJSONForHTTPResponse($content);

    $response = CelerityAPI::getStaticResourceResponse();
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
