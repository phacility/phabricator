<?php

final class PhabricatorTypeaheadTokenView
  extends AphrontTagView {

  private $key;
  private $icon;
  private $inputName;
  private $value;

  public static function newForTypeaheadResult(
    PhabricatorTypeaheadResult $result) {

    return id(new PhabricatorTypeaheadTokenView())
      ->setKey($result->getPHID())
      ->setIcon($result->getIcon())
      ->setValue($result->getDisplayName());
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setInputName($input_name) {
    $this->inputName = $input_name;
    return $this;
  }

  public function getInputName() {
    return $this->inputName;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  protected function getTagName() {
    return 'a';
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'jx-tokenizer-token',
    );
  }

  protected function getTagContent() {
    $input_name = $this->getInputName();
    if ($input_name) {
      $input_name .= '[]';
    }

    $value = $this->getValue();

    $icon = $this->getIcon();
    if ($icon) {
      $value = array(
        phutil_tag(
          'span',
          array(
            'class' => 'phui-icon-view phui-font-fa bluetext '.$icon,
          )),
        $value,
      );
    }

    return array(
      $value,
      phutil_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $input_name,
          'value' => $this->getKey(),
        )),
      phutil_tag('span', array('class' => 'jx-tokenizer-x-placeholder'), ''),
    );
  }

}
