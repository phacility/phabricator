<?php

abstract class PhabricatorUserCacheType extends Phobject {

  final public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function getAutoloadKeys() {
    return array();
  }

  public function canManageKey($key) {
    return false;
  }

  public function getDefaultValue() {
    return array();
  }

  public function shouldValidateRawCacheData() {
    return false;
  }

  public function isRawCacheDataValid(PhabricatorUser $user, $key, $data) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getValueFromStorage($value) {
    return $value;
  }

  public function newValueForUsers($key, array $users) {
    return array();
  }

  final public function getUserCacheType() {
    return $this->getPhobjectClassConstant('CACHETYPE');
  }

  public static function getAllCacheTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getUserCacheType')
      ->execute();
  }

  public static function getCacheTypeForKey($key) {
    $all = self::getAllCacheTypes();

    foreach ($all as $type) {
      if ($type->canManageKey($key)) {
        return $type;
      }
    }

    return null;
  }

  public static function requireCacheTypeForKey($key) {
    $type = self::getCacheTypeForKey($key);

    if (!$type) {
      throw new Exception(
        pht(
          'Failed to load UserCacheType to manage key "%s". This cache type '.
          'is required.',
          $key));
    }

    return $type;
  }

}
