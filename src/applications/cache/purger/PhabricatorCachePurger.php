<?php

abstract class PhabricatorCachePurger
  extends Phobject {

  private $viewer;

  abstract public function purgeCache();

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function getPurgerKey() {
    return $this->getPhobjectClassConstant('PURGERKEY');
  }

  final public static function getAllPurgers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPurgerKey')
      ->execute();
  }

}
