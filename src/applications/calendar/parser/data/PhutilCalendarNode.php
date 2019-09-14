<?php

abstract class PhutilCalendarNode extends Phobject {

  private $attributes = array();

  final public function getNodeType() {
    return $this->getPhobjectClassConstant('NODETYPE');
  }

  final public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  final public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

}
