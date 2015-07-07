<?php

abstract class CelerityPostprocessor
  extends Phobject {

  private $default;

  abstract public function getPostprocessorKey();
  abstract public function getPostprocessorName();
  abstract public function buildVariables();

  public function buildDefaultPostprocessor() {
    return new CelerityDefaultPostprocessor();
  }

  final public function getVariables() {
    $variables = $this->buildVariables();

    $default = $this->getDefault();
    if ($default) {
      $variables += $default->getVariables();
    }

    return $variables;
  }

  final public function getDefault() {
    if ($this->default === null) {
      $this->default = $this->buildDefaultPostprocessor();
    }
    return $this->default;
  }

  final public static function getPostprocessor($key) {
    return idx(self::getAllPostprocessors(), $key);
  }

  final public static function getAllPostprocessors() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPostprocessorKey')
      ->execute();
  }

}
