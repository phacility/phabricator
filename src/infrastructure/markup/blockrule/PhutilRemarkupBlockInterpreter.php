<?php

abstract class PhutilRemarkupBlockInterpreter extends Phobject {

  private $engine;

  final public function setEngine($engine) {
    $this->engine = $engine;
    return $this;
  }

  final public function getEngine() {
    return $this->engine;
  }

  /**
   * @return string
   */
  abstract public function getInterpreterName();

  abstract public function markupContent($content, array $argv);

  protected function markupError($string) {
    if ($this->getEngine()->isTextMode()) {
      return '('.$string.')';
    } else {
      return phutil_tag(
        'div',
        array(
          'class' => 'remarkup-interpreter-error',
        ),
        $string);
    }
  }

}
