<?php

abstract class PhabricatorMailExternalMessage
  extends Phobject {

  final public function getMessageType() {
    return $this->getPhobjectClassConstant('MESSAGETYPE');
  }

  final public static function getAllMessageTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getMessageType')
      ->execute();
  }

}
