<?php

abstract class AphrontView {

  protected $children = array();

  final public function appendChild($child) {
    $this->children[] = $child;
    return $this;
  }

  final protected function renderChildren() {
    $out = array();
    foreach ($this->children as $child) {
      $out[] = $this->renderSingleView($child);
    }
    return implode('', $out);
  }

  final protected function renderSingleView($child) {
    if ($child instanceof AphrontView) {
      return $child->render();
    } else if (is_array($child)) {
      $out = array();
      foreach ($child as $element) {
        $out[] = $this->renderSingleView($element);
      }
      return implode('', $out);
    } else {
      return $child;
    }
  }

  abstract public function render();

  public function __set($name, $value) {
    phlog('Wrote to undeclared property '.get_class($this).'::$'.$name.'.');
    $this->$name = $value;
  }

}
