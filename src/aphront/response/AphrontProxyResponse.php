<?php

/**
 * Base class for responses which augment other types of responses. For example,
 * a response might be substantially an Ajax response, but add structure to the
 * response content. It can do this by extending @{class:AphrontProxyResponse},
 * instantiating an @{class:AphrontAjaxResponse} in @{method:buildProxy}, and
 * then constructing a real @{class:AphrontAjaxResponse} in
 * @{method:reduceProxyResponse}.
 */
abstract class AphrontProxyResponse
  extends AphrontResponse
  implements AphrontResponseProducerInterface {

  private $proxy;

  protected function getProxy() {
    if (!$this->proxy) {
      $this->proxy = $this->buildProxy();
    }
    return $this->proxy;
  }

  public function setRequest($request) {
    $this->getProxy()->setRequest($request);
    return $this;
  }

  public function getRequest() {
    return $this->getProxy()->getRequest();
  }

  public function getHeaders() {
    return $this->getProxy()->getHeaders();
  }

  public function setCacheDurationInSeconds($duration) {
    $this->getProxy()->setCacheDurationInSeconds($duration);
    return $this;
  }

  public function setLastModified($epoch_timestamp) {
    $this->getProxy()->setLastModified($epoch_timestamp);
    return $this;
  }

  public function setHTTPResponseCode($code) {
    $this->getProxy()->setHTTPResponseCode($code);
    return $this;
  }

  public function getHTTPResponseCode() {
    return $this->getProxy()->getHTTPResponseCode();
  }

  public function setFrameable($frameable) {
    $this->getProxy()->setFrameable($frameable);
    return $this;
  }

  public function getCacheHeaders() {
    return $this->getProxy()->getCacheHeaders();
  }

  abstract protected function buildProxy();
  abstract public function reduceProxyResponse();

  final public function buildResponseString() {
    throw new Exception(
      pht(
        '%s must implement %s.',
        __CLASS__,
        'reduceProxyResponse()'));
  }


/* -(  AphrontResponseProducerInterface  )----------------------------------- */


  public function produceAphrontResponse() {
    return $this->reduceProxyResponse();
  }

}
