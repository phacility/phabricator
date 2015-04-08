<?php

final class PhabricatorDataCacheSpec extends PhabricatorCacheSpec {

  private $cacheSummary;

  public function setCacheSummary(array $cache_summary) {
    $this->cacheSummary = $cache_summary;
    return $this;
  }

  public function getCacheSummary() {
    return $this->cacheSummary;
  }

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

    $cache = $info['cache_list'];
    $state = array();
    foreach ($cache as $item) {
      $key = self::getKeyPattern($item['info']);
      if (empty($state[$key])) {
        $state[$key] = array(
          'max' => 0,
          'total' => 0,
          'count' => 0,
        );
      }
      $state[$key]['max'] = max($state[$key]['max'], $item['mem_size']);
      $state[$key]['total'] += $item['mem_size'];
      $state[$key]['count']++;
    }

    $spec->setCacheSummary($state);
  }

  private static function getKeyPattern($key) {
    // If this key isn't in the current cache namespace, don't reveal any
    // information about it.
    $namespace = PhabricatorEnv::getEnvConfig('phabricator.cache-namespace');
    if (strncmp($key, $namespace.':', strlen($namespace) + 1)) {
      return '<other-namespace>';
    }

    $key = preg_replace('/(?<![a-zA-Z])\d+(?![a-zA-Z])/', 'N', $key);
    $key = preg_replace('/PHID-[A-Z]{4}-[a-z0-9]{20}/', 'PHID', $key);

    // TODO: We should probably standardize how digests get embedded into cache
    // keys to make this rule more generic.
    $key = preg_replace('/:celerity:.*$/', ':celerity:X', $key);
    $key = preg_replace('/:pkcs8:.*$/', ':pkcs8:X', $key);

    return $key;
  }

}
