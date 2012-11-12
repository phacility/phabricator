<?php

final class AphrontTokenizerTemplateView extends AphrontView {

  private $value;
  private $name;
  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setValue(array $value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function render() {
    require_celerity_resource('aphront-tokenizer-control-css');

    $id = $this->id;
    $name = $this->getName();
    $values = nonempty($this->getValue(), array());

    $tokens = array();
    foreach ($values as $key => $value) {
      $tokens[] = $this->renderToken($key, $value);
    }

    $input = javelin_render_tag(
      'input',
      array(
        'mustcapture' => true,
        'name'        => $name,
        'class'       => 'jx-tokenizer-input',
        'sigil'       => 'tokenizer-input',
        'style'       => 'width: 0px;',
        'disabled'    => 'disabled',
        'type'        => 'text',
      ));

    return phutil_render_tag(
      'div',
      array(
        'id' => $id,
        'class' => 'jx-tokenizer-container',
      ),
      implode('', $tokens).
      $input.
      '<div style="clear: both;"></div>');
  }

  private function renderToken($key, $value) {
    $input_name = $this->getName();
    if ($input_name) {
      $input_name .= '[]';
    }
    return phutil_render_tag(
      'a',
      array(
        'class' => 'jx-tokenizer-token',
      ),
      phutil_escape_html($value).
      phutil_render_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $input_name,
          'value' => $key,
        )).
      '<span class="jx-tokenizer-x-placeholder"></span>');
  }

}
