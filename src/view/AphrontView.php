<?php

abstract class AphrontView extends Phobject {

  protected $user;
  protected $children = array();

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  protected function getUser() {
    return $this->user;
  }

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

  final protected function renderHTMLChildren() {
    $out = array();
    foreach ($this->children as $child) {
      $out[] = $this->renderHTMLView($child);
    }
    return $out;
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

  final protected function renderHTMLView($child) {
    if ($child instanceof AphrontView) {
      return phutil_safe_html($child->render());
    } else if ($child instanceof PhutilSafeHTML) {
      return $child;
    } else if (is_array($child)) {
      $out = array();
      foreach ($child as $element) {
        $out[] = $this->renderHTMLView($element);
      }
      return phutil_safe_html(implode('', $out));
    } else {
      return phutil_safe_html(phutil_escape_html($child));
    }
  }

  abstract public function render();

}
