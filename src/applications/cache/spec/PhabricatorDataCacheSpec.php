<?php

final class PhabricatorDataCacheSpec extends PhabricatorCacheSpec {

  public static function getActiveCacheSpec() {
    $spec = new PhabricatorDataCacheSpec();
    // NOTE: If APCu is installed, it reports that APC is installed.
    if (extension_loaded('apc') && !extension_loaded('apcu')) {
      return self::getAPCSpec($spec);
    } else if (extension_loaded('apcu')) {
      return self::getAPCuSpec($spec);
    } else {
      return self::getNoneSpec($spec);
    }
  }

  private static function getAPCSpec(PhabricatorDataCacheSpec $spec) {
    $spec
      ->setName(pht('APC User Cache'))
      ->setVersion(phpversion('apc'));

    if (ini_get('apc.enabled')) {
      $spec->setIsEnabled(true);
      self::getAPCCommonSpec($spec);
    } else {
      $spec->setIsEnabled(false);
      $spec->newIssue(
        pht('Enable APC'),
        pht(
          'The "APC" extension is currently disabled. Set "apc.enabled" to '.
          'true to provide caching.'),
        'apc.enabled');
    }

    return $spec;
  }

  private static function getAPCuSpec(PhabricatorDataCacheSpec $spec) {
    $spec
      ->setName(pht('APCu'))
      ->setVersion(phpversion('apcu'));

    if (ini_get('apc.enabled')) {
      $spec->setIsEnabled(true);
      self::getAPCCommonSpec($spec);
    } else {
      $spec->setIsEnabled(false);
      $spec->newissue(
        pht('Enable APCu'),
        pht(
          'The "APCu" extension is currently disabled. Set '.
          '"apc.enabled" to true to provide caching.'),
        'apc.enabled');
    }

    return $spec;
  }

  private static function getNoneSpec(PhabricatorDataCacheSpec $spec) {
    if (version_compare(phpversion(), '5.5', '>=')) {
      $spec->newIssue(
        pht('Install APCu'),
        pht(
          'Install the "APCu" PHP extension to provide data caching.'));
    } else {
      $spec->newIssue(
        pht('Install APC'),
        pht(
          'Install the "APC" PHP extension to provide data caching.'));
    }

    return $spec;
  }

  private static function getAPCCommonSpec(PhabricatorDataCacheSpec $spec) {
    $mem = apc_sma_info();
    $spec->setTotalMemory($mem['num_seg'] * $mem['seg_size']);

    $info = apc_cache_info('user');
    $spec->setUsedMemory($info['mem_size']);
    $spec->setEntryCount(count($info['cache_list']));
  }

}
