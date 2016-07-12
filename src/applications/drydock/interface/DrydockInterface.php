<?php

abstract class DrydockInterface extends Phobject {

  private $config = array();

  abstract public function getInterfaceType();

  final public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  final protected function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

}
