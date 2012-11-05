<?php

final class AphrontFormInsetView extends AphrontView {

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

    $title = $hidden_inputs = $right_button = $desc = $content = '';

    if ($this->title) {
      $title = '<h1>'.phutil_escape_html($this->title).'</h1>';
    }

    $hidden_inputs = array();
    foreach ($this->hidden as $inp) {
      list($key, $value) = $inp;
      $hidden_inputs[] = phutil_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }
    $hidden_inputs = implode("\n", $hidden_inputs);

    if ($this->rightButton) {
      $right_button = phutil_render_tag(
        'div',
        array(
          'style' => 'float: right;',
        ),
        $this->rightButton);
    }

    if ($this->description) {
      $desc = phutil_render_tag(
        'p',
        array(),
        $this->description);

      if ($right_button) {
        $desc .= '<div style="clear: both;"></div>';
      }
    }

    $div_attributes = $this->divAttributes;
    $classes = array('aphront-form-inset');
    if (isset($div_attributes['class'])) {
      $classes[] = $div_attributes['class'];
    }

    $div_attributes['class'] = implode(' ', $classes);

    if ($this->content) {
      $content = $this->content;
    }

    return $title.phutil_render_tag(
      'div',
      $div_attributes,
      $hidden_inputs.$right_button.$desc.$content.$this->renderChildren());
  }
}
