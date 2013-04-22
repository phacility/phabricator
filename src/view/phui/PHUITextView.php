<?php

final class PHUITextView extends AphrontTagView {

  private $text;

  public function setText($text) {
    $this->appendChild($text);
    return $this;
  }

  public function getTagName() {
    return 'span';
  }

  public function getTagAttributes() {
    require_celerity_resource('phui-text-css');
    return array();
  }
}
