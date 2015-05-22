<?php

/**
 * @concrete-extensible
 */
class PHUIFormPageView extends AphrontView {

  private $key;
  private $form;
  private $controls = array();
  private $content = array();
  private $values = array();
  private $isValid;
  private $validateFormPageCallback;
  private $adjustFormPageCallback;
  private $pageErrors = array();
  private $pageName;


  public function setPageName($page_name) {
    $this->pageName = $page_name;
    return $this;
  }

  public function getPageName() {
    return $this->pageName;
  }

  public function addPageError($page_error) {
    $this->pageErrors[] = $page_error;
    return $this;
  }

  public function getPageErrors() {
    return $this->pageErrors;
  }

  public function setAdjustFormPageCallback($adjust_form_page_callback) {
    $this->adjustFormPageCallback = $adjust_form_page_callback;
    return $this;
  }

  public function setValidateFormPageCallback($validate_form_page_callback) {
    $this->validateFormPageCallback = $validate_form_page_callback;
    return $this;
  }

  public function addInstructions($text, $before = null) {
    $tag = phutil_tag(
      'div',
      array(
        'class' => 'aphront-form-instructions',
      ),
      $text);

    $append = true;
    if ($before !== null) {
      for ($ii = 0; $ii < count($this->content); $ii++) {
        if ($this->content[$ii] instanceof AphrontFormControl) {
          if ($this->content[$ii]->getName() == $before) {
            array_splice($this->content, $ii, 0, array($tag));
            $append = false;
            break;
          }
        }
      }
    }

    if ($append) {
      $this->content[] = $tag;
    }

    return $this;
  }

  public function addRemarkupInstructions($remarkup, $before = null) {
    return $this->addInstructions(
      PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($remarkup),
        'default',
        $this->getUser()), $before);
  }

  public function addControl(AphrontFormControl $control) {
    $name = $control->getName();

    if (!strlen($name)) {
      throw new Exception(pht('Form control has no name!'));
    }

    if (isset($this->controls[$name])) {
      throw new Exception(
        pht("Form page contains duplicate control with name '%s'!", $name));
    }

    $this->controls[$name] = $control;
    $this->content[] = $control;
    $control->setFormPage($this);

    return $this;
  }

  public function getControls() {
    return $this->controls;
  }

  public function getControl($name) {
    if (empty($this->controls[$name])) {
      throw new Exception(pht("No page control '%s'!", $name));
    }
    return $this->controls[$name];
  }

  protected function canAppendChild() {
    return false;
  }

  public function setPagedFormView(PHUIPagedFormView $view, $key) {
    if ($this->key) {
      throw new Exception(pht('This page is already part of a form!'));
    }
    $this->form = $view;
    $this->key = $key;
    return $this;
  }

  public function adjustFormPage() {
    if ($this->adjustFormPageCallback) {
      call_user_func($this->adjustFormPageCallback, $this);
    }
    return $this;
  }

  protected function validateFormPage() {
    if ($this->validateFormPageCallback) {
      return call_user_func($this->validateFormPageCallback, $this);
    }
    return true;
  }

  public function getKey() {
    return $this->key;
  }

  public function render() {
    return $this->content;
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
      $this->isValid = $this->validateControls() && $this->validateFormPage();
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
    foreach ($this->getControls() as $name => $control) {
      if (is_array($object)) {
        $control->readValueFromDictionary($object);
      }
    }

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
