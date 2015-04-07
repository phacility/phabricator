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

      $mem = apc_sma_info();
      $spec->setTotalMemory($mem['num_seg'] * $mem['seg_size']);

      $info = apc_cache_info();
      $spec->setUsedMemory($info['mem_size']);
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

      $status = opcache_get_status();
      $memory = $status['memory_usage'];

      $mem_used = $memory['used_memory'];
      $mem_free = $memory['free_memory'];
      $mem_junk = $memory['wasted_memory'];
      $spec->setUsedMemory($mem_used + $mem_junk);
      $spec->setTotalMemory($mem_used + $mem_junk + $mem_free);
      $spec->setEntryCount($status['opcache_statistics']['num_cached_keys']);
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
