<?php

final class AphrontTypeaheadTemplateView extends AphrontView {

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
    require_celerity_resource('aphront-typeahead-control-css');

    $id = $this->id;
    $name = $this->getName();
    $values = nonempty($this->getValue(), array());

    $tokens = array();
    foreach ($values as $key => $value) {
      $tokens[] = $this->renderToken($key, $value);
    }

    $input = javelin_tag(
      'input',
      array(
        'name'          => $name,
        'class'         => 'jx-typeahead-input',
        'sigil'         => 'typeahead',
        'type'          => 'text',
        'value'         => $this->value,
        'autocomplete'  => 'off',
      ));

    return javelin_tag(
      'div',
      array(
        'id'    => $id,
        'sigil' => 'typeahead-hardpoint',
        'class' => 'jx-typeahead-hardpoint',
      ),
      array(
        $input,
        phutil_tag('div', array('style' => 'clear: both'), ''),
      ));
  }
}
