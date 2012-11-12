<?php

abstract class DrydockInterface {

  private $config;

  abstract public function getInterfaceType();

  final public function setConfiguration(array $config) {
    $this->config = $config;
    return $this;
  }

  final protected function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

}
