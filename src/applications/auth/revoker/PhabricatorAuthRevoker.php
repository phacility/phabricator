<?php

abstract class PhabricatorAuthRevoker
  extends Phobject {

  private $viewer;

  abstract public function revokeAlLCredentials();
  abstract public function revokeCredentialsFrom($object);

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function getRevokerKey() {
    return $this->getPhobjectClassConstant('REVOKERKEY');
  }

  final public static function getAllRevokers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getRevokerKey')
      ->execute();
  }

}
