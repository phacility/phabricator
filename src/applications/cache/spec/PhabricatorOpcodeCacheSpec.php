<?php

final class PhabricatorOpcodeCacheSpec extends PhabricatorCacheSpec {

  public static function getActiveCacheSpec() {
    $spec = new PhabricatorOpcodeCacheSpec();
    // NOTE: If APCu is installed, it reports that APC is installed.
    if (extension_loaded('apc') && !extension_loaded('apcu')) {
      return self::getAPCSpec($spec);
    } else if (extension_loaded('Zend OPcache')) {
      return self::getOpcacheSpec($spec);
    } else {
      return self::getNoneSpec($spec);
    }
  }

  private static function getAPCSpec(PhabricatorOpcodeCacheSpec $spec) {
    $spec
      ->setName(pht('APC'))
      ->setVersion(phpversion('apc'));

    if (ini_get('apc.enabled')) {
      $spec->setIsEnabled(true);
    } else {
      $spec->setIsEnabled(false);
      $spec->newIssue(
        pht('Enable APC'),
        pht(
          'The "APC" extension is currently disabled. Set "apc.enabled" to '.
          'true to improve performance.'),
        'apc.enabled');
    }

    return $spec;
  }

  private static function getOpcacheSpec(PhabricatorOpcodeCacheSpec $spec) {
    $spec
      ->setName(pht('Zend OPcache'))
      ->setVersion(phpversion('Zend OPcache'));

    if (ini_get('opcache.enable')) {
      $spec->setIsEnabled(true);
    } else {
      $spec->setIsEnabled(false);
      $spec->newissue(
        pht('Enable Zend OPcache'),
        pht(
          'The "Zend OPcache" extension is currently disabled. Set '.
          '"opcache.enable" to true to improve performance.'),
        'opcache.enable');
    }

    return $spec;
  }

  private static function getNoneSpec(PhabricatorOpcodeCacheSpec $spec) {
    if (version_compare(phpversion(), '5.5', '>=')) {
      $spec->newIssue(
        pht('Install OPcache'),
        pht(
          'Install the "Zend OPcache" PHP extension to improve performance.'));
    } else {
      $spec->newIssue(
        pht('Install APC'),
        pht(
          'Install the "APC" PHP extension to improve performance.'));
    }

    return $spec;
  }

}
