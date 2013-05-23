<?php

/**
 * @concrete-extensible
 */
class PHUIFormPageView extends AphrontView {

  private $key;
  private $form;
  private $controls = array();
  private $values = array();
  private $isValid;

  public function addControl(AphrontFormControl $control) {
    $name = $control->getName();

    if (!strlen($name)) {
      throw new Exception("Form control has no name!");
    }

    if (isset($this->controls[$name])) {
      throw new Exception(
        "Form page contains duplicate control with name '{$name}'!");
    }

    $this->controls[$name] = $control;
    $control->setFormPage($this);

    return $this;
  }

  public function getControls() {
    return $this->controls;
  }

  public function getControl($name) {
    if (empty($this->controls[$name])) {
      throw new Exception("No page control '{$name}'!");
    }
    return $this->controls[$name];
  }

  protected function canAppendChild() {
    return false;
  }

  public function setPagedFormView(PHUIPagedFormView $view, $key) {
    if ($this->key) {
      throw new Exception("This page is already part of a form!");
    }
    $this->form = $view;
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function render() {
    return $this->getControls();
  }

  public function getForm() {
    return $this->form;
  }

  public function getRequestKey($key) {
    return $this->getForm()->getRequestKey('p:'.$this->key.':'.$key);
  }

  public function validateObjectType($object) {
    return true;
  }

  public function validateResponseType($response) {
    return true;
  }

  protected function validateControls() {
    $result = true;
    foreach ($this->getControls() as $name => $control) {
      if (!$control->isValid()) {
        $result = false;
        break;
      }
    }

    return $result;
  }

  public function isValid() {
    if ($this->isValid === null) {
      $this->isValid = $this->validateControls();
    }
    return $this->isValid;
  }

  public function readFromRequest(AphrontRequest $request) {
    foreach ($this->getControls() as $name => $control) {
      $control->readValueFromRequest($request);
    }

    return $this;
  }

  public function readFromObject($object) {
    return $this;
  }

  public function writeToResponse($response) {
    return $this;
  }

  public function readSerializedValues(AphrontRequest $request) {
    foreach ($this->getControls() as $name => $control) {
      $key = $this->getRequestKey($name);
      $control->readSerializedValue($request->getStr($key));
    }

    return $this;
  }

  public function getSerializedValues() {
    $dict = array();
    foreach ($this->getControls() as $name => $control) {
      $key = $this->getRequestKey($name);
      $dict[$key] = $control->getSerializedValue();
    }
    return $dict;
  }

}
