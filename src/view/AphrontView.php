<?php

abstract class AphrontView extends Phobject
  implements PhutilSafeHTMLProducerInterface {

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
        pht("View '%s' does not support children.", $class));
    }
    $this->children[] = $child;
    return $this;
  }

  final protected function renderChildren() {
    return $this->children;
  }

  /**
   * @deprecated
   */
  final protected function renderSingleView($child) {
    phutil_deprecated(
      'AphrontView->renderSingleView()',
      "This method no longer does anything; it can be removed and replaced ".
      "with its arguments.");
    return $child;
  }

  final protected function isEmptyContent($content) {
    if (is_array($content)) {
      foreach ($content as $element) {
        if (!$this->isEmptyContent($element)) {
          return false;
        }
      }
      return true;
    } else {
      return !strlen((string)$content);
    }
  }

  abstract public function render();

  public function producePhutilSafeHTML() {
    return $this->render();
  }

}
