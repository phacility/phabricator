<?php

abstract class AphrontFormControl extends AphrontView {

  private $label;
  private $caption;
  private $error;
  private $name;
  private $value;
  private $disabled;
  private $id;
  private $controlID;
  private $controlStyle;
  private $formPage;
  private $required;
  private $hidden;
  private $classes;

  public function setHidden($hidden) {
    $this->hidden = $hidden;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function getID() {
    return $this->id;
  }

  public function setControlID($control_id) {
    $this->controlID = $control_id;
    return $this;
  }

  public function getControlID() {
    return $this->controlID;
  }

  public function setControlStyle($control_style) {
    $this->controlStyle = $control_style;
    return $this;
  }

  public function getControlStyle() {
    return $this->controlStyle;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setCaption($caption) {
    $this->caption = $caption;
    return $this;
  }

  public function getCaption() {
    return $this->caption;
  }

  public function setError($error) {
    $this->error = $error;
    return $this;
  }

  public function getError() {
    return $this->error;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function isValid() {
    if ($this->error && $this->error !== true) {
      return false;
    }

    if ($this->isRequired() && $this->isEmpty()) {
      return false;
    }

    return true;
  }

  public function isRequired() {
    return $this->required;
  }

  public function isEmpty() {
    return !strlen($this->getValue());
  }

  public function getSerializedValue() {
    return $this->getValue();
  }

  public function readSerializedValue($value) {
    $this->setValue($value);
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getName()));
    return $this;
  }

  public function readValueFromDictionary(array $dictionary) {
    $this->setValue(idx($dictionary, $this->getName()));
    return $this;
  }

  public function setFormPage(PHUIFormPageView $page) {
    if ($this->formPage) {
      throw new Exception('This control is already a member of a page!');
    }
    $this->formPage = $page;
    return $this;
  }

  public function getFormPage() {
    if ($this->formPage === null) {
      throw new Exception('This control does not have a page!');
    }
    return $this->formPage;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  abstract protected function renderInput();
  abstract protected function getCustomControlClass();

  protected function shouldRender() {
    return true;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  final public function render() {
    if (!$this->shouldRender()) {
      return null;
    }

    $custom_class = $this->getCustomControlClass();

    // If we don't have an ID yet, assign an automatic one so we can associate
    // the label with the control. This allows assistive technologies to read
    // form labels.
    if (!$this->getID()) {
      $this->setID(celerity_generate_unique_node_id());
    }

    $input = phutil_tag(
      'div',
      array('class' => 'aphront-form-input'),
      $this->renderInput());

    $error = null;
    if (strlen($this->getError())) {
      $error = $this->getError();
      if ($error === true) {
        $error = phutil_tag(
          'span',
          array('class' => 'aphront-form-error aphront-form-required'),
          pht('Required'));
      } else {
        $error = phutil_tag(
          'span',
          array('class' => 'aphront-form-error'),
          $error);
      }
    }

    if (strlen($this->getLabel())) {
      $label = phutil_tag(
        'label',
        array(
          'class' => 'aphront-form-label',
          'for' => $this->getID(),
        ),
        array(
          $this->getLabel(),
          $error,
        ));
    } else {
      $label = null;
      $custom_class .= ' aphront-form-control-nolabel';
    }

    if (strlen($this->getCaption())) {
      $caption = phutil_tag(
        'div',
        array('class' => 'aphront-form-caption'),
        $this->getCaption());
    } else {
      $caption = null;
    }

    $classes = array();
    $classes[] = 'aphront-form-control';
    $classes[] = 'grouped';
    $classes[] = $custom_class;
    if ($this->classes) {
      foreach ($this->classes as $class) {
        $classes[] = $class;
      }
    }

    $style = $this->controlStyle;
    if ($this->hidden) {
      $style = 'display: none; '.$style;
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
        'id' => $this->controlID,
        'style' => $style,
      ),
      array(
        $label,
        $error,
        $input,
        $caption,
      ));
  }
}
