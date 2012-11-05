<?php

/**
 * @group aphront
 */
abstract class AphrontController {

  private $request;
  private $currentApplication;

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

  public function __set($name, $value) {
    phlog('Wrote to undeclared property '.get_class($this).'::$'.$name.'.');
    $this->$name = $value;
  }

}
