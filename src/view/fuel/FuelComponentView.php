<?php

abstract class FuelComponentView
  extends FuelView {

  private $classes = array();

  final public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  private function getClasses() {
    return $this->classes;
  }

  final protected function newComponentTag(
    $tag,
    array $attributes,
    $content) {

    $classes = $this->getClasses();
    if (isset($attributes['class'])) {
      $classes[] = $attributes['class'];
    }

    if ($classes) {
      $classes = implode(' ', $classes);
      $attributes['class'] = $classes;
    }

    return javelin_tag($tag, $attributes, $content);
  }

}
