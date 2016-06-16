<?php

final class PhabricatorKeyring extends Phobject {

  private static $hasReadConfiguration;
  private static $keyRing = array();

  public static function addKey($spec) {
    self::$keyRing[$spec['name']] = $spec;
  }

  public static function getKey($name, $type) {
    self::readConfiguration();

    if (empty(self::$keyRing[$name])) {
      throw new Exception(
        pht(
          'No key "%s" exists in keyring.',
          $name));
    }

    $spec = self::$keyRing[$name];

    $material = base64_decode($spec['material.base64'], true);
    return new PhutilOpaqueEnvelope($material);
  }

  public static function getDefaultKeyName($type) {
    self::readConfiguration();

    foreach (self::$keyRing as $name => $key) {
      if (!empty($key['default'])) {
        return $name;
      }
    }

    return null;
  }

  private static function readConfiguration() {
    if (self::$hasReadConfiguration) {
      return true;
    }

    self::$hasReadConfiguration = true;

    foreach (PhabricatorEnv::getEnvConfig('keyring') as $spec) {
      self::addKey($spec);
    }
  }

}
