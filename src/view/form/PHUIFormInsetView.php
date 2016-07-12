<?php

final class PHUIFormInsetView extends AphrontView {

  private $title;
  private $description;
  private $rightButton;
  private $content;
  private $hidden = array();

  private $divAttributes;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function setRightButton($button) {
    $this->rightButton = $button;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function addHiddenInput($key, $value) {
    if (is_array($value)) {
      foreach ($value as $hidden_key => $hidden_value) {
        $this->hidden[] = array($key.'['.$hidden_key.']', $hidden_value);
      }
    } else {
      $this->hidden[] = array($key, $value);
    }
    return $this;
  }

  public function addDivAttributes(array $attributes) {
    $this->divAttributes = $attributes;
    return $this;
  }

  public function render() {

    $right_button = $desc = '';

    $hidden_inputs = array();
    foreach ($this->hidden as $inp) {
      list($key, $value) = $inp;
      $hidden_inputs[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }

    if ($this->rightButton) {
      $right_button = phutil_tag(
        'div',
        array(
          'style' => 'float: right;',
        ),
        $this->rightButton);
      $right_button = phutil_tag_div('grouped', $right_button);
    }

    if ($this->description) {
      $desc = phutil_tag(
        'p',
        array(),
        $this->description);
    }

    $div_attributes = $this->divAttributes;
    $classes = array('phui-form-inset');
    if (isset($div_attributes['class'])) {
      $classes[] = $div_attributes['class'];
    }

    $div_attributes['class'] = implode(' ', $classes);

    $content = $hidden_inputs;
    $content[] = $right_button;
    $content[] = $desc;

    if ($this->title != '') {
      array_unshift($content, phutil_tag('h1', array(), $this->title));
    }

    if ($this->content) {
      $content[] = $this->content;
    }

    $content = array_merge($content, $this->renderChildren());

    return phutil_tag('div', $div_attributes, $content);
  }
}
