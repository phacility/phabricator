<?php

abstract class PhabricatorAuthLoginHandler
  extends Phobject {

  private $request;
  private $delegatingController;

  public function getAuthLoginHeaderContent() {
    return array();
  }

  final public function setDelegatingController(AphrontController $controller) {
    $this->delegatingController = $controller;
    return $this;
  }

  final public function getDelegatingController() {
    return $this->delegatingController;
  }

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public static function getAllHandlers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }
}
