<?php

abstract class Vacation extends Phobject {

  private $request;
  private $requestHandler;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }
  
  public function setRequestHandler($handler) {
      $this->requestHandler = $handler;
  }
  
  public function getRequestHandler() {
      return $this->requestHandler;
  }

  abstract public function getName();
  abstract public function getDescription();
  abstract public function renderPage($user);
  abstract protected function getPanelType();

  public function getCategory() {
    return pht('General');
  }
}
