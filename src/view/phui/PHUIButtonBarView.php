<?php

final class PHUIButtonBarView extends AphrontTagView {

  private $buttons = array();
  private $borderless;

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function setBorderless($borderless) {
    $this->borderless = $borderless;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-button-bar';
    if ($this->borderless) {
      $classes[] = 'phui-button-bar-borderless';
    }
    return array('class' => implode(' ', $classes));
  }

  protected function getTagName() {
    return 'span';
  }

  protected function getTagContent() {
    require_celerity_resource('phui-button-bar-css');

    $i = 1;
    $j = count($this->buttons);
    foreach ($this->buttons as $button) {
      // LeeLoo Dallas Multi-Pass
      if ($j > 1) {
        if ($i == 1) {
          $button->addClass('phui-button-bar-first');
        } else if ($i == $j) {
          $button->addClass('phui-button-bar-last');
        } else if ($j > 1) {
          $button->addClass('phui-button-bar-middle');
        }
      }
      $this->appendChild($button);
      $i++;
    }

    return $this->renderChildren();
  }
}
