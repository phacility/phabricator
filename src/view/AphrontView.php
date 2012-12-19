<?php

abstract class AphrontView extends Phobject {

  protected $children = array();

  protected function canAppendChild() {
    return true;
  }

  final public function appendChild($child) {
    if (!$this->canAppendChild()) {
      $class = get_class($this);
      throw new Exception(
        "View '{$class}' does not support children.");
    }
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

}
