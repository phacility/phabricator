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
    assert_instances_of($value, 'PhabricatorObjectHandle');
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
      $tokens[] = $this->renderToken(
        $value->getPHID(),
        $value->getFullName(),
        $value->getType());
    }

    $input = javelin_tag(
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

    $content = $tokens;
    $content[] = $input;
    $content[] = phutil_tag('div', array('style' => 'clear: both;'), '');

    return phutil_tag(
      'div',
      array(
        'id' => $id,
        'class' => 'jx-tokenizer-container',
      ),
      $content);
  }

  private function renderToken($key, $value, $icon) {
    return id(new PhabricatorTypeaheadTokenView())
      ->setKey($key)
      ->setValue($value)
      ->setIcon($icon)
      ->setInputName($this->getName());
  }

}
