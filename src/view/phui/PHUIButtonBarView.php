<?php

final class PHUIButtonBarView extends AphrontTagView {

  private $buttons = array();

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  protected function getTagAttributes() {
    return array('class' => 'phui-button-bar');
  }

  public function getTagName() {
    return 'div';
  }

  public function getTagContent() {
    require_celerity_resource('phui-button-css');

    $i = 1;
    $j = count($this->buttons);
    foreach ($this->buttons as $button) {
      // LeeLoo Dallas Multi-Pass
      if ($j > 1) {
        if ($i == 1) {
          $button->addClass('phui-button-bar-first');
        } elseif ($i == $j) {
          $button->addClass('phui-button-bar-last');
        } elseif ($j > 1) {
          $button->addClass('phui-button-bar-middle');
        }
      }
      $this->appendChild($button);
      $i++;
    }

    return $this->renderChildren();
  }
}
