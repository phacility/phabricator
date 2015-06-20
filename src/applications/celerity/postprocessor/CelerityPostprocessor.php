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
    static $postprocessors;

    if ($postprocessors === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $object) {
        $key = $object->getPostprocessorKey();
        if (empty($map[$key])) {
          $map[$key] = $object;
          continue;
        }

        throw new Exception(
          pht(
            'Two postprocessors (of classes "%s" and "%s") define the same '.
            'postprocessor key ("%s"). Each postprocessor must define a '.
            'unique key.',
            get_class($object),
            get_class($map[$key]),
            $key));
      }
      $postprocessors = $map;
    }

    return $postprocessors;
  }

}
