<?php

abstract class PhabricatorUserLogType
  extends Phobject {

  final public function getLogTypeKey() {
    return $this->getPhobjectClassConstant('LOGTYPE', 32);
  }

  abstract public function getLogTypeName();

  final public static function getAllLogTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLogTypeKey')
      ->execute();
  }

}
