<?php

abstract class PhabricatorAuthMessageType
  extends Phobject {

  final public function getMessageTypeKey() {
    return $this->getPhobjectClassConstant('MESSAGEKEY', 64);
  }

  final public static function getAllMessageTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getMessageTypeKey')
      ->execute();
  }

  final public static function newFromKey($key) {
    $types = self::getAllMessageTypes();

    if (empty($types[$key])) {
      throw new Exception(
        pht(
          'No message type exists with key "%s".',
          $key));
    }

    return clone $types[$key];
  }

  abstract public function getDisplayName();
  abstract public function getShortDescription();

  public function getFullDescription() {
    return null;
  }

  public function getDefaultMessageText() {
    return null;
  }

}
