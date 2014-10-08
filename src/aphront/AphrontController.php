<?php

abstract class AphrontController extends Phobject {

  private $request;
  private $currentApplication;
  private $delegatingController;

  public function setDelegatingController(
    AphrontController $delegating_controller) {
    $this->delegatingController = $delegating_controller;
    return $this;
  }

  public function getDelegatingController() {
    return $this->delegatingController;
  }

  public function willBeginExecution() {
    return;
  }

  public function willProcessRequest(array $uri_data) {
    return;
  }

  public function didProcessRequest($response) {
    return $response;
  }

  abstract public function processRequest();

  final public function __construct(AphrontRequest $request) {
    $this->request = $request;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function delegateToController(AphrontController $controller) {
    $controller->setDelegatingController($this);

    $application = $this->getCurrentApplication();
    if ($application) {
      $controller->setCurrentApplication($application);
    }

    return $controller->processRequest();
  }

  final public function setCurrentApplication(
    PhabricatorApplication $current_application) {

    $this->currentApplication = $current_application;
    return $this;
  }

  final public function getCurrentApplication() {
    return $this->currentApplication;
  }

  public function getDefaultResourceSource() {
    throw new Exception(
      pht(
        'A Controller must implement getDefaultResourceSource() before you '.
        'can invoke requireResource() or initBehavior().'));
  }

  public function requireResource($symbol) {
    $response = CelerityAPI::getStaticResourceResponse();
    $response->requireResource($symbol, $this->getDefaultResourceSource());
    return $this;
  }

  public function initBehavior($name, $config = array()) {
    Javelin::initBehavior(
      $name,
      $config,
      $this->getDefaultResourceSource());
  }

}
