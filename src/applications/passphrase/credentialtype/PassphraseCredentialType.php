<?php

abstract class PassphraseCredentialType extends Phobject {

  abstract public function getCredentialType();
  abstract public function getProvidesType();
  abstract public function getCredentialTypeName();
  abstract public function getCredentialTypeDescription();
  abstract public function getSecretLabel();

  public function newSecretControl() {
    return new AphrontFormTextAreaControl();
  }

  public static function getAllTypes() {
    $types = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->loadObjects();
    return $types;
  }

  public static function getTypeByConstant($constant) {
    $all = self::getAllTypes();
    $all = mpull($all, null, 'getCredentialType');
    return idx($all, $constant);
  }

}
