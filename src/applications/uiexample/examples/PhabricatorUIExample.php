<?php

abstract class PhabricatorUIExample {

  private $request;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  abstract public function getName();
  abstract public function getDescription();
  abstract public function renderExample();

}
