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

  public function handleRequest(AphrontRequest $request) {
    if (method_exists($this, 'processRequest')) {
      return $this->processRequest();
    }

    throw new PhutilMethodNotImplementedException(
      pht(
        'Controllers must implement either %s (recommended) '.
        'or %s (deprecated).',
        'handleRequest()',
        'processRequest()'));
  }

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    if (!$this->request) {
      throw new PhutilInvalidStateException('setRequest');
    }
    return $this->request;
  }

  final public function getViewer() {
    return $this->getRequest()->getViewer();
  }

  final public function delegateToController(AphrontController $controller) {
    $request = $this->getRequest();

    $controller->setDelegatingController($this);
    $controller->setRequest($request);

    $application = $this->getCurrentApplication();
    if ($application) {
      $controller->setCurrentApplication($application);
    }

    return $controller->handleRequest($request);
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
    throw new PhutilMethodNotImplementedException(
      pht(
        'A Controller must implement %s before you can invoke %s or %s.',
        'getDefaultResourceSource()',
        'requireResource()',
        'initBehavior()'));
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
