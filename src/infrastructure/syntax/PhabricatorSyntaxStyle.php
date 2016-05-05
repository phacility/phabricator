<?php

abstract class PhabricatorSyntaxStyle extends Phobject {

  abstract public function getStyleName();
  abstract public function getStyleMap();

  final public function getStyleOrder() {
    return (string)id(new PhutilSortVector())
      ->addInt($this->isDefaultStyle() ? 0 : 1)
      ->addString($this->getStyleName());
  }

  final public function getSyntaxStyleKey() {
    return $this->getPhobjectClassConstant('STYLEKEY');
  }

  final public function isDefaultStyle() {
    return ($this->getSyntaxStyleKey() == 'default');
  }

  public static function getAllStyles() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getSyntaxStyleKey')
      ->setSortMethod('getStyleName')
      ->execute();
  }

}
