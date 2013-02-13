<?php

final class PhabricatorMainMenuGroupView extends AphrontView {

  private $collapsible = true;
  private $classes = array();

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setCollapsible($collapsible) {
    $this->collapsible = $collapsible;
    return $this;
  }

  public function render() {
    $classes = array(
      'phabricator-main-menu-group',
    );

    if ($this->collapsible) {
      $classes[] = 'phabricator-main-menu-collapsible';
    }

    if ($this->classes) {
      $classes = array_merge($classes, $this->classes);
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->renderChildren());
  }

}
