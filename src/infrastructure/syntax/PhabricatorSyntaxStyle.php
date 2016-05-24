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

  final public function getRemarkupStyleMap() {
    $map = array(
      'rbw_r' => 'color: red',
      'rbw_o' => 'color: orange',
      'rbw_y' => 'color: yellow',
      'rbw_g' => 'color: green',
      'rbw_b' => 'color: blue',
      'rbw_i' => 'color: indigo',
      'rbw_v' => 'color: violet',
    );

    return $map + $this->getStyleMap();
  }

}
