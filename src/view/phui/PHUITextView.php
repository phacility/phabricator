<?php

final class PHUITextView extends AphrontTagView {

  private $text;

  public function setText($text) {
    $this->appendChild($text);
    return $this;
  }

  protected function getTagName() {
    return 'span';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-text-css');
    return array();
  }
}
