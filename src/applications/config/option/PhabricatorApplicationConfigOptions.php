<?php

abstract class PhabricatorApplicationConfigOptions extends Phobject {

  abstract public function getName();
  abstract public function getDescription();
  abstract public function getOptions();

  public function getKey() {
    $class = get_class($this);
    $matches = null;
    if (preg_match('/^Phabricator(.*)ConfigOptions$/', $class, $matches)) {
      return strtolower($matches[1]);
    }
    return strtolower(get_class($this));
  }

  final protected function newOption($key, $type, $default) {
    return id(new PhabricatorConfigOption())
      ->setKey($key)
      ->setType($type)
      ->setDefault($default)
      ->setGroup($this);
  }

  final public static function loadAll() {
    $symbols = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorApplicationConfigOptions')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $groups = array();
    foreach ($symbols as $symbol) {
      $obj = newv($symbol['name'], array());
      $key = $obj->getKey();
      if (isset($groups[$key])) {
        $pclass = get_class($groups[$key]);
        $nclass = $symbol['name'];

        throw new Exception(
          "Multiple PhabricatorApplicationConfigOptions subclasses have the ".
          "same key ('{$key}'): {$pclass}, {$nclass}.");
      }
      $groups[$key] = $obj;
    }

    return $groups;
  }

  final public static function loadAllOptions() {
    $groups = self::loadAll();

    $options = array();
    foreach ($groups as $group) {
      foreach ($group->getOptions() as $option) {
        $key = $option->getKey();
        if (isset($options[$key])) {
          throw new Exception(
            "Mulitple PhabricatorApplicationConfigOptions subclasses contain ".
            "an option named '{$key}'!");
        }
        $options[$key] = $option;
      }
    }

    return $options;
  }


}
