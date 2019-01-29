<?php

final class PhabricatorAmazonSNSFuture extends PhutilAWSFuture {
  private $parameters = array();
  private $timeout;

  public function setParameters($parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  protected function getParameters() {
    return $this->parameters;
  }

  public function getServiceName() {
    return 'sns';
  }

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  protected function getProxiedFuture() {
    $future = parent::getProxiedFuture();

    $timeout = $this->getTimeout();
    if ($timeout) {
      $future->setTimeout($timeout);
    }

    return $future;

  }

}
