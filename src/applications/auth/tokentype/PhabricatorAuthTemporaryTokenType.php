<?php

abstract class PhabricatorAuthTemporaryTokenType
  extends Phobject {

  abstract public function getTokenTypeDisplayName();
  abstract public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token);

  public function isTokenRevocable(PhabricatorAuthTemporaryToken $token) {
    return false;
  }

  final public function getTokenTypeConstant() {
    return $this->getPhobjectClassConstant('TOKENTYPE', 64);
  }

  final public static function getAllTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTokenTypeConstant')
      ->execute();
  }

}
