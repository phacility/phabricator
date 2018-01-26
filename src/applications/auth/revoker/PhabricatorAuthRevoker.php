<?php

abstract class PhabricatorAuthRevoker
  extends Phobject {

  private $viewer;

  abstract public function revokeAllCredentials();
  abstract public function revokeCredentialsFrom($object);

  abstract public function getRevokerName();
  abstract public function getRevokerDescription();

  public function getRevokerNextSteps() {
    return null;
  }

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
